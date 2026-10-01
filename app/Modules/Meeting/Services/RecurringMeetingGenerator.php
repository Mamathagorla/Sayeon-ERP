<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Meeting\Models\RecurringMeetingModel;
use App\Modules\Meeting\Models\RecurringMeetingParticipantModel;
use App\Modules\Notification\Services\NotificationService;

/**
 * The single place that turns a recurring_meetings template into real
 * `meetings` rows — same "exactly one write path" design as
 * RecurringExpenseGenerator, which this mirrors closely. The one thing
 * beyond that precedent: each generated meeting also gets the template's
 * standing attendee list copied into meeting_participants, and each of
 * those attendees gets the same in-app "meeting link" notification a
 * manually-added participant already gets (ParticipantController::store()).
 */
class RecurringMeetingGenerator
{
    private RecurringMeetingModel $recurringModel;
    private MeetingModel $meetingModel;
    private RecurringMeetingParticipantModel $templateParticipantModel;
    private MeetingParticipantModel $participantModel;
    private NotificationService $notificationService;

    // Same defensive-cap reasoning as RecurringExpenseGenerator::MAX_PER_RUN.
    private const MAX_PER_RUN = 500;

    public function __construct(
        ?RecurringMeetingModel $recurringModel = null,
        ?MeetingModel $meetingModel = null,
        ?RecurringMeetingParticipantModel $templateParticipantModel = null,
        ?MeetingParticipantModel $participantModel = null,
        ?NotificationService $notificationService = null
    ) {
        $this->recurringModel = $recurringModel ?? new RecurringMeetingModel();
        $this->meetingModel   = $meetingModel ?? new MeetingModel();
        $this->templateParticipantModel = $templateParticipantModel ?? new RecurringMeetingParticipantModel();
        $this->participantModel = $participantModel ?? new MeetingParticipantModel();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Automatic trigger — same once-per-calendar-day cache gate as
     * RecurringExpenseGenerator::runOncePerDay(), under its own cache key
     * so the two generators never block each other.
     */
    public function runOncePerDay(): void
    {
        $cache = cache();
        $today = date('Y-m-d');

        if ($cache->get('recurring_meetings_last_run') === $today) {
            return;
        }
        $cache->save('recurring_meetings_last_run', $today, 172800);

        try {
            $result = $this->runAll();
            if ($result['meetingsGenerated'] > 0) {
                log_message('info', "Recurring meetings (auto): {$result['meetingsGenerated']} meeting(s) generated from {$result['templatesProcessed']} template(s).");
            }
        } catch (\Throwable $e) {
            log_message('error', 'Recurring meetings auto-run failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{templatesProcessed:int, meetingsGenerated:int}
     */
    public function runAll(): array
    {
        return $this->run(null);
    }

    /**
     * @return array{templatesProcessed:int, meetingsGenerated:int}
     */
    public function runForCompany(int $companyId): array
    {
        return $this->run($companyId);
    }

    private function run(?int $companyId): array
    {
        $templates          = $this->recurringModel->dueForGeneration($companyId);
        $templatesProcessed = 0;
        $meetingsGenerated   = 0;

        foreach ($templates as $template) {
            $generated = $this->runOne($template);
            if ($generated > 0) {
                $templatesProcessed++;
                $meetingsGenerated += $generated;
            }
        }

        return ['templatesProcessed' => $templatesProcessed, 'meetingsGenerated' => $meetingsGenerated];
    }

    /**
     * Catches up a single template, exactly like
     * RecurringExpenseGenerator::runOne() — generates every occurrence
     * whose date has already arrived, idempotent via the
     * uniq_recurring_meeting_occurrence index (checked belt-and-
     * suspenders below, same as the Expense generator does for its own
     * unique index).
     */
    public function runOne(array $template): int
    {
        $generatedCount = 0;
        $participants    = $this->templateParticipantModel->forTemplate((int) $template['id']);

        for ($i = 0; $i < self::MAX_PER_RUN; $i++) {
            if ($template['status'] !== 'active') {
                break;
            }
            if ($template['next_generation_date'] === null) {
                break;
            }
            if ($template['next_generation_date'] > date('Y-m-d')) {
                break;
            }

            $occurrenceNumber = (int) $template['occurrences_generated'] + 1;

            $alreadyExists = $this->meetingModel
                ->where('recurring_meeting_id', $template['id'])
                ->where('occurrence_number', $occurrenceNumber)
                ->countAllResults() > 0;

            if ($alreadyExists) {
                break;
            }

            $meetingId = $this->meetingModel->insert([
                'company_id'           => $template['company_id'],
                'recurring_meeting_id' => $template['id'],
                'occurrence_number'    => $occurrenceNumber,
                'title'                => $template['title'],
                'agenda'               => $template['agenda'],
                'meeting_date'         => $template['next_generation_date'],
                'start_time'           => $template['start_time'],
                'end_time'             => $template['end_time'],
                'location'             => $template['location'],
                'meeting_link'         => $template['meeting_link'],
                'created_by'           => $template['created_by'],
            ]);

            foreach ($participants as $p) {
                $this->participantModel->insert([
                    'meeting_id' => $meetingId,
                    'user_id'    => $p['user_id'],
                ]);

                if ((int) $p['user_id'] !== (int) $template['created_by']) {
                    $message = 'A new occurrence of "' . $template['title'] . '" is scheduled on ' . $template['next_generation_date'] . '.';
                    if (! empty($template['meeting_link'])) {
                        $message .= ' Join at: ' . $template['meeting_link'];
                    }

                    $this->notificationService->notify(
                        (int) $p['user_id'],
                        'meeting_invite',
                        'Meeting scheduled: ' . $template['title'],
                        $message,
                        'meeting',
                        $meetingId
                    );
                }
            }

            $generatedCount++;

            $template['occurrences_generated'] = $occurrenceNumber;
            $template['next_generation_date']  = $this->recurringModel->advanceDate($template['next_generation_date'], $template['frequency']);

            $reachedOccurrenceLimit = $template['end_type'] === 'occurrences'
                && $template['occurrences_total'] !== null
                && $occurrenceNumber >= (int) $template['occurrences_total'];

            $reachedEndDate = $template['end_type'] === 'end_date'
                && $template['end_date'] !== null
                && $template['next_generation_date'] > $template['end_date'];

            if ($reachedOccurrenceLimit || $reachedEndDate) {
                $template['status']               = 'completed';
                $template['next_generation_date'] = null;
            }

            $this->recurringModel->update($template['id'], [
                'occurrences_generated' => $template['occurrences_generated'],
                'next_generation_date'  => $template['next_generation_date'],
                'status'                => $template['status'],
            ]);
        }

        return $generatedCount;
    }
}
