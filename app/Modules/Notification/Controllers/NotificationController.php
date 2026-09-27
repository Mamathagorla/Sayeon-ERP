<?php

namespace App\Modules\Notification\Controllers;

use App\Controllers\BaseController;
use App\Modules\Notification\Models\NotificationModel;
use App\Modules\Notification\Services\NotificationService;

class NotificationController extends BaseController
{
    protected NotificationModel $notificationModel;
    protected NotificationService $notificationService;

    // Modules where related_id maps directly to a viewable detail page.
    private const LINK_BASE = [
        'task'        => '/tasks/',
        'compliance'  => '/compliance/',
        'meeting'     => '/meetings/',
        'project'     => '/projects/',
        'performance' => '/hr/performance/',
        'website'     => '/websites/',
    ];

    // Modules with no single-record detail view (or where the id points
    // somewhere the recipient may not have access to, e.g. a payroll run
    // that lists every employee's pay) â€” link to a fixed, always-safe page.
    private const LINK_FIXED = [
        'leave'   => '/hr/leave',
        'payroll' => '/hr/payroll/my-payslips',
        'expense' => '/expenses',
    ];

    public function __construct()
    {
        $this->notificationModel   = new NotificationModel();
        $this->notificationService = new NotificationService();
    }

    /**
     * Full "My Notifications" page.
     */
    public function index()
    {
        $userId = (int) $this->currentUserId();
        $this->notificationService->sweepFor($userId);

        $onlyUnread = $this->request->getGet('unread') === '1';

        return view('App\Modules\Notification\index', [
            'title'         => 'Notifications',
            'navActive'     => 'notifications',
            'notifications' => $this->withLinks($this->notificationModel->allFor($userId, $onlyUnread)),
            'onlyUnread'    => $onlyUnread,
        ]);
    }

    /**
     * JSON feed for the navbar bell â€” fetched on every page via the
     * layout's inline script. Also the trigger point for the due-date
     * sweep, though sweepFor() throttles the actual scan to once every
     * few minutes per session, so most of these calls are just a fast
     * read of already-swept notifications.
     */
    public function recent()
    {
        $userId = (int) $this->currentUserId();
        $this->notificationService->sweepFor($userId);

        return $this->response->setJSON([
            'unreadCount'   => $this->notificationModel->unreadCountFor($userId),
            'notifications' => $this->withLinks($this->notificationModel->recentFor($userId, 8)),
        ]);
    }

    public function markRead(int $id)
    {
        $this->notificationModel->markRead($id, (int) $this->currentUserId());

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true]);
        }

        return redirect()->back();
    }

    public function markAllRead()
    {
        $this->notificationModel->markAllRead((int) $this->currentUserId());

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true]);
        }

        return redirect()->to('/notifications')->with('success', 'All notifications marked as read.');
    }

    private function withLinks(array $notifications): array
    {
        foreach ($notifications as &$n) {
            $module = $n['related_module'];

            if ($module && isset(self::LINK_BASE[$module]) && $n['related_id']) {
                $n['link'] = site_url(ltrim(self::LINK_BASE[$module], '/') . $n['related_id']);
            } elseif ($module && isset(self::LINK_FIXED[$module])) {
                $n['link'] = site_url(ltrim(self::LINK_FIXED[$module], '/'));
            } else {
                $n['link'] = null;
            }
        }

        return $notifications;
    }
}
