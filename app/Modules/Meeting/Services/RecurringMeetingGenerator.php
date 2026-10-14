<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Meeting\Models\RecurringMeetingModel;
use App\Modules\Meeting\Models\RecurringMeetingParticipantModel;
use App\Modules\Notification\Services\NotificationService;
use DateTime;

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
        $meetingsGenerated  = 0;

        foreach ($templates as $template) {
            try {
                $generated = $this->runOne($template);
            } catch (\Throwable $e) {
                // One broken template must not stop the rest of the run;
                // its transaction was already rolled back in runOne().
                log_message('error', "Recurring meeting template #{$template['id']} failed: " . $e->getMessage());
                continue;
            }

            if ($generated > 0) {
                $templatesProcessed++;
                $meetingsGenerated += $generated;
            }
        }

        return ['templatesProcessed' => $templatesProcessed, 'meetingsGenerated' => $meetingsGenerated];
    }

    /**
     * Catches up a single template: generates every occurrence whose date
     * has arrived, in one transaction under a row lock, so a daily run,
     * "Generate Now" and the CLI command can never both produce the same
     * occurrence. An occurrence that already exists is consumed (the
     * schedule advances past it) rather than halting the template, so a
     * stale or pre-existing meeting can never freeze the schedule.
     */
    public function runOne(array $template): int
    {
        $generatedCount = 0;
        $participants   = $this->templateParticipantModel->forTemplate((int) $template['id']);
        $db             = db_connect();

        $db->transStart();

        $locked = $db->query('SELECT * FROM recurring_meetings WHERE id = ? FOR UPDATE', [(int) $template['id']])->getRowArray();
        if ($locked === null) {
            $db->transComplete();

            return 0;
        }
        $template = $locked;

        try {
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
                $date             = $template['next_generation_date'];

                $alreadyExists = $this->meetingModel
                    ->where('recurring_meeting_id', $template['id'])
                    ->where('occurrence_number', $occurrenceNumber)
                    ->countAllResults() > 0;

                if (! $alreadyExists) {
                    $generatedCount += $this->createOccurrence($template, $occurrenceNumber, $date, $participants);
                }

                $template['occurrences_generated'] = $occurrenceNumber;
                $template['next_generation_date']  = $this->recurringModel->advanceDate($date, $template['frequency']);

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
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new \RuntimeException('Recurring meeting generation rolled back for template #' . $template['id']);
        }

        return $generatedCount;
    }

    /**
     * Writes one meeting for an occurrence, copying the template's times,
     * overnight end date and timezone exactly. For an overnight template the
     * end date is the day after the occurrence date, so end is always later
     * than start in real time.
     */
    private function createOccurrence(array $template, int $occurrenceNumber, string $date, array $participants): int
    {
        $endsNextDay = (bool) (int) ($template['ends_next_day'] ?? 0);
        $endDate     = $endsNextDay ? (new DateTime($date))->modify('+1 day')->format('Y-m-d') : null;

        $meetingId = $this->meetingModel->insert([
            'company_id'           => $template['company_id'],
            'recurring_meeting_id' => $template['id'],
            'occurrence_number'    => $occurrenceNumber,
            'title'                => $template['title'],
            'agenda'               => $template['agenda'],
            'meeting_date'         => $date,
            'end_date'             => $endDate,
            'start_time'           => $template['start_time'],
            'end_time'             => $template['end_time'],
            'timezone'             => $template['timezone'] ?? config('App')->appTimezone,
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
                $message = 'A new occurrence of "' . $template['title'] . '" is scheduled on ' . $date . '.';
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

        return 1;
    }
}
