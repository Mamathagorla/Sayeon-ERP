<?php

namespace App\Modules\Support\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Support\Models\SupportTicketAttachmentModel;
use App\Modules\Support\Models\SupportTicketMessageModel;
use App\Modules\Support\Models\SupportTicketModel;

class SupportController extends BaseController
{
    protected SupportTicketModel $ticketModel;
    protected SupportTicketMessageModel $messageModel;
    protected SupportTicketAttachmentModel $attachmentModel;
    protected EmployeeProfileModel $employeeModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->ticketModel         = new SupportTicketModel();
        $this->messageModel        = new SupportTicketMessageModel();
        $this->attachmentModel     = new SupportTicketAttachmentModel();
        $this->employeeModel       = new EmployeeProfileModel();
        $this->companyModel        = new CompanyModel();
        $this->userModel           = new UserModel();
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $isResolver = $this->isResolver();
        $filters    = array_filter($this->request->getGet(['status']) ?? []);

        if ($isResolver) {
            $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
            if ($companyScope !== null) {
                $filters['company_id'] = $companyScope;
            }
        } else {
            // Everyone else only ever sees their own tickets — same
            // self-scoping precedent as PayrollController::show()
            // redirecting Employee to myPayslips() instead of the
            // org-wide view.
            $filters['raised_by'] = $this->currentUserId();
        }

        return view('App\Modules\Support\index', [
            'title'          => 'Help & Support',
            'navActive'      => 'help',
            'tickets'        => $this->ticketModel->filtered($filters)->findAll(),
            'statuses'       => SupportTicketModel::STATUSES,
            'categoryLabels' => SupportTicketModel::CATEGORY_LABELS,
            'filters'        => $filters,
            'isResolver'     => $isResolver,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Support\form', [
            'title'          => 'Raise a Ticket',
            'navActive'      => 'help',
            'categoryLabels' => SupportTicketModel::CATEGORY_LABELS,
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->ticketModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = $this->currentCompanyId();
        $priority  = $this->request->getPost('priority') ?: 'medium';

        $id = $this->ticketModel->insert([
            'company_id'  => $companyId,
            'subject'     => $this->request->getPost('subject'),
            'description' => $this->request->getPost('description'),
            'category'    => $this->request->getPost('category') ?: 'general',
            'priority'    => $priority,
            'due_date'    => $this->ticketModel->dueDateFor($priority),
            'raised_by'   => $this->currentUserId(),
        ]);

        $subject = $this->request->getPost('subject');
        $this->logActivity('support_ticket', 'create', $id, 'Raised support ticket: ' . $subject);
        $this->notifyResolvers(
            $companyId,
            $id,
            'support_ticket_new',
            'New support ticket: ' . $subject,
            'A new support ticket was raised: "' . $subject . '".'
        );

        return redirect()->to('/help/' . $id)->with('success', 'Ticket raised. Support will follow up soon.');
    }

    public function show(int $id)
    {
        $ticket = $this->ticketModel->withRelations($id);

        if ($ticket === null || ! $this->canView($ticket)) {
            return redirect()->to('/help')->with('error', 'Ticket not found.');
        }

        $isResolverViewer = $this->isResolver() && ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, $ticket['company_id']);
        $messages          = $this->messageModel->forTicket($id, $isResolverViewer);

        return view('App\Modules\Support\show', [
            'title'                => $ticket['subject'],
            'navActive'            => 'help',
            'ticket'               => $ticket,
            'messages'             => $messages,
            'attachmentsByMessage' => $this->attachmentModel->forMessages(array_column($messages, 'id')),
            'statuses'             => SupportTicketModel::STATUSES,
            'priorities'           => SupportTicketModel::PRIORITIES,
            'categoryLabels'       => SupportTicketModel::CATEGORY_LABELS,
            'assignees'            => $isResolverViewer ? $this->scopedUserOptions($this->userModel->listForOptions()) : [],
            'sla'                  => $this->ticketModel->slaStatus($ticket),
            'isResolver'           => $isResolverViewer,
        ]);
    }

    /**
     * Posts a reply into the ticket's thread — the raiser and any
     * in-scope resolver can both reach this (same canView() gate as
     * show()); only a resolver viewer may mark it an internal note
     * ($isInternal is forced false otherwise, never trusted from POST
     * alone).
     */
    public function reply(int $id)
    {
        $ticket = $this->ticketModel->find($id);

        if ($ticket === null || ! $this->canView($ticket)) {
            return redirect()->to('/help')->with('error', 'Ticket not found.');
        }

        $message = trim((string) $this->request->getPost('message'));

        if ($message === '') {
            return redirect()->to('/help/' . $id)->with('error', 'Reply cannot be empty.');
        }

        $isResolverViewer = $this->isResolver() && ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, $ticket['company_id']);
        $isInternal       = $isResolverViewer && $this->request->getPost('is_internal_note') === '1';

        $messageId = $this->messageModel->insert([
            'ticket_id'        => $id,
            'user_id'          => $this->currentUserId(),
            'message'          => $message,
            'is_internal_note' => $isInternal,
        ]);

        $file = $this->request->getFile('attachment');

        if ($file !== null && $file->isValid() && ! $file->hasMoved()) {
            if ($file->getSize() > 20 * 1024 * 1024) {
                return redirect()->to('/help/' . $id)->with('error', 'Attachment exceeds the 20MB limit.');
            }

            $storage = service('fileStorage');
            $path    = $storage->store($file, "support_tickets/{$id}");

            $this->attachmentModel->insert([
                'message_id'    => $messageId,
                'file_path'     => $path,
                'original_name' => $file->getClientName(),
                'file_size'     => $file->getSize(),
            ]);
        }

        $this->logActivity(
            'support_ticket',
            'reply',
            $id,
            ($isInternal ? 'Added an internal note to' : 'Replied to') . ' ticket "' . $ticket['subject'] . '"'
        );

        if (! $isInternal) {
            if ($isResolverViewer) {
                if ((int) $ticket['raised_by'] !== (int) $this->currentUserId()) {
                    $this->notificationService->notify(
                        (int) $ticket['raised_by'],
                        'support_ticket_reply',
                        'New reply: ' . $ticket['subject'],
                        'There is a new reply on your ticket "' . $ticket['subject'] . '".',
                        'support_ticket',
                        $id
                    );
                }
            } elseif ($ticket['assigned_to'] && (int) $ticket['assigned_to'] !== (int) $this->currentUserId()) {
                $this->notificationService->notify(
                    (int) $ticket['assigned_to'],
                    'support_ticket_reply',
                    'New reply: ' . $ticket['subject'],
                    'There is a new reply on ticket "' . $ticket['subject'] . '".',
                    'support_ticket',
                    $id
                );
            } else {
                $this->notifyResolvers(
                    $ticket['company_id'],
                    $id,
                    'support_ticket_reply',
                    'New reply: ' . $ticket['subject'],
                    'There is a new reply on ticket "' . $ticket['subject'] . '".'
                );
            }
        }

        return redirect()->to('/help/' . $id . '#reply-' . $messageId)->with('success', 'Reply sent.');
    }

    /**
     * The resolver action — status/priority/assignee, gated by
     * support_ticket.edit at the route level (Company Admin + Super
     * Admin only) and further narrowed here to the resolver's own
     * company scope, same two-layer pattern as every other module's
     * HR/owner-only action.
     */
    public function resolve(int $id)
    {
        $ticket = $this->ticketModel->find($id);

        if ($ticket === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $ticket['company_id'])) {
            return redirect()->to('/help')->with('error', 'Ticket not found.');
        }

        $status     = $this->request->getPost('status');
        $priority   = $this->request->getPost('priority');
        $assignedTo = $this->request->getPost('assigned_to') ?: null;

        if (! in_array($status, SupportTicketModel::STATUSES, true)) {
            return redirect()->to('/help/' . $id)->with('error', 'Invalid status.');
        }
        if (! in_array($priority, SupportTicketModel::PRIORITIES, true)) {
            return redirect()->to('/help/' . $id)->with('error', 'Invalid priority.');
        }

        if ($assignedTo !== null) {
            $validIds = array_map('intval', array_column($this->scopedUserOptions($this->userModel->listForOptions()), 'id'));
            if (! in_array((int) $assignedTo, $validIds, true)) {
                return redirect()->to('/help/' . $id)->with('error', 'Invalid assignee.');
            }
        }

        $data = [
            'status'      => $status,
            'priority'    => $priority,
            'assigned_to' => $assignedTo,
        ];

        if (in_array($status, ['resolved', 'closed'], true) && $ticket['resolved_at'] === null) {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }
        if (! in_array($status, ['resolved', 'closed'], true)) {
            $data['resolved_at'] = null;
        }

        $this->ticketModel->update($id, $data);
        $this->logActivity('support_ticket', 'update', $id, 'Updated ticket "' . $ticket['subject'] . '" to ' . $status);

        if ((int) $ticket['raised_by'] !== (int) $this->currentUserId()) {
            $this->notificationService->notify(
                (int) $ticket['raised_by'],
                'support_ticket_' . $status,
                'Ticket update: ' . $ticket['subject'],
                'Your support ticket "' . $ticket['subject'] . '" is now ' . str_replace('_', ' ', $status) . '.',
                'support_ticket',
                $id
            );
        }

        return redirect()->to('/help/' . $id)->with('success', 'Ticket updated.');
    }

    public function delete(int $id)
    {
        $ticket = $this->ticketModel->find($id);

        if ($ticket !== null) {
            if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $ticket['company_id'])) {
                return redirect()->to('/help')->with('error', 'Ticket not found.');
            }

            $this->ticketModel->delete($id);
            $this->logActivity('support_ticket', 'delete', $id, 'Deleted ticket: ' . $ticket['subject']);
        }

        return redirect()->to('/help')->with('success', 'Ticket removed.');
    }

    private function isResolver(): bool
    {
        return can('support_ticket.edit');
    }

    private function canView(array $ticket): bool
    {
        if ((int) $ticket['raised_by'] === (int) $this->currentUserId()) {
            return true;
        }

        return $this->isResolver() && ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, $ticket['company_id']);
    }

    /**
     * Derives the raiser's company server-side (never trusted from the
     * browser — same fix as OffboardingController's earlier bug):
     * whoever has an employee_profiles row uses its company_id; Super
     * Admin (no profile) gets a null "general/HQ" ticket instead of a
     * forced company pick, since they aren't tied to one.
     */
    private function currentCompanyId(): ?int
    {
        $profile = $this->employeeModel->byUserId((int) $this->currentUserId());

        return $profile['company_id'] ?? null;
    }

    /**
     * Notifies every Company Admin of the ticket's company (there's no
     * single fixed "resolver" user to target, unlike Onboarding's
     * reporting_manager_id) — silently does nothing for a general/
     * no-company ticket, which only Super Admin can raise and Super
     * Admin doesn't receive notifications anywhere else in this app
     * either. Shared by store() (new ticket) and reply() (raiser reply
     * with nobody yet assigned).
     */
    private function notifyResolvers(?int $companyId, int $ticketId, string $type, string $title, string $message): void
    {
        if ($companyId === null) {
            return;
        }

        $admins = db_connect()->table('employee_profiles ep')
            ->select('users.id')
            ->join('users', 'users.id = ep.user_id')
            ->join('roles', 'roles.id = users.role_id')
            ->where('ep.company_id', $companyId)
            ->where('roles.slug', 'admin')
            ->get()->getResultArray();

        foreach ($admins as $admin) {
            $this->notificationService->notify((int) $admin['id'], $type, $title, $message, 'support_ticket', $ticketId);
        }
    }
}
