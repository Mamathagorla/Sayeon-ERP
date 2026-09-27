<?php

/** @var CodeIgniter\Router\RouteCollection $routes */

$routes->group('hr', ['namespace' => 'App\Modules\HR\Controllers'], static function ($routes) {
    // Employees
    $routes->group('employees', static function ($routes) {
        $routes->get('/', 'EmployeeController::index', ['filter' => 'permission:employee.view']);
        $routes->get('create', 'EmployeeController::create', ['filter' => 'permission:employee.create']);
        $routes->post('/', 'EmployeeController::store', ['filter' => 'permission:employee.create']);
        $routes->get('(:num)', 'EmployeeController::show/$1', ['filter' => 'permission:employee.view']);
        $routes->get('(:num)/edit', 'EmployeeController::edit/$1', ['filter' => 'permission:employee.edit']);
        $routes->post('(:num)', 'EmployeeController::update/$1', ['filter' => 'permission:employee.edit']);
        $routes->post('(:num)/delete', 'EmployeeController::delete/$1', ['filter' => 'permission:employee.delete']);
    });

    // Attendance
    $routes->group('attendance', static function ($routes) {
        $routes->get('/', 'AttendanceController::index', ['filter' => 'permission:attendance.view']);
        $routes->get('mine', 'AttendanceController::mine', ['filter' => 'permission:attendance.view']);
        $routes->get('employee/(:num)', 'AttendanceController::employee/$1', ['filter' => 'permission:attendance.view']);
        $routes->post('check-in', 'AttendanceController::checkIn', ['filter' => 'permission:attendance.create']);
        $routes->post('check-out', 'AttendanceController::checkOut', ['filter' => 'permission:attendance.create']);
    });

    // Leave
    $routes->group('leave', static function ($routes) {
        $routes->get('/', 'LeaveController::index', ['filter' => 'permission:leave.view']);
        $routes->get('create', 'LeaveController::create', ['filter' => 'permission:leave.create']);
        $routes->get('employee/(:num)', 'LeaveController::employee/$1', ['filter' => 'permission:leave.view']);
        $routes->post('/', 'LeaveController::store', ['filter' => 'permission:leave.create']);
        $routes->post('(:num)/approve', 'LeaveController::approve/$1', ['filter' => 'permission:leave.approve']);
        $routes->post('(:num)/reject', 'LeaveController::reject/$1', ['filter' => 'permission:leave.approve']);
        $routes->post('(:num)/cancel', 'LeaveController::cancel/$1', ['filter' => 'permission:leave.create']);

        $routes->get('types', 'LeaveTypeController::index', ['filter' => 'permission:leave.edit']);
        $routes->post('types', 'LeaveTypeController::store', ['filter' => 'permission:leave.edit']);
        $routes->post('types/(:num)/delete', 'LeaveTypeController::delete/$1', ['filter' => 'permission:leave.edit']);
    });

    // Payroll
    $routes->group('payroll', static function ($routes) {
        $routes->get('/', 'PayrollController::index', ['filter' => 'permission:payroll.view']);
        $routes->post('generate', 'PayrollController::generate', ['filter' => 'permission:payroll.create']);
        $routes->get('my-payslips', 'PayrollController::myPayslips', ['filter' => 'permission:payroll.view']);
        $routes->get('employee/(:num)', 'PayrollController::employee/$1', ['filter' => 'permission:payroll.view']);
        $routes->get('structure/(:num)', 'PayrollController::editStructure/$1', ['filter' => 'permission:payroll.edit']);
        $routes->post('structure/(:num)', 'PayrollController::saveStructure/$1', ['filter' => 'permission:payroll.edit']);
        $routes->get('payslip/(:num)', 'PayrollController::payslip/$1', ['filter' => 'permission:payroll.view']);
        $routes->get('payslip/(:num)/pdf', 'PayrollController::payslipPdf/$1', ['filter' => 'permission:payroll.view']);
        $routes->get('(:num)', 'PayrollController::show/$1', ['filter' => 'permission:payroll.view']);
        $routes->post('(:num)/mark-paid', 'PayrollController::markPaid/$1', ['filter' => 'permission:payroll.edit']);
    });

    // Performance
    $routes->group('performance', static function ($routes) {
        $routes->get('/', 'PerformanceController::index', ['filter' => 'permission:performance.view']);
        $routes->get('cycles', 'PerformanceController::cycles', ['filter' => 'permission:performance.edit']);
        $routes->post('cycles', 'PerformanceController::storeCycle', ['filter' => 'permission:performance.edit']);
        $routes->get('create', 'PerformanceController::create', ['filter' => 'permission:performance.create']);
        $routes->get('employee/(:num)', 'PerformanceController::employee/$1', ['filter' => 'permission:performance.view']);
        $routes->post('/', 'PerformanceController::store', ['filter' => 'permission:performance.create']);
        $routes->get('(:num)', 'PerformanceController::show/$1', ['filter' => 'permission:performance.view']);
        $routes->get('(:num)/edit', 'PerformanceController::edit/$1', ['filter' => 'permission:performance.edit']);
        $routes->post('(:num)', 'PerformanceController::update/$1', ['filter' => 'permission:performance.edit']);
        $routes->post('(:num)/submit', 'PerformanceController::submit/$1', ['filter' => 'permission:performance.edit']);
        $routes->post('(:num)/acknowledge', 'PerformanceController::acknowledge/$1', ['filter' => 'permission:performance.view']);
    });
});
