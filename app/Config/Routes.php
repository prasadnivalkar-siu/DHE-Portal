<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index', ['as' => 'home']);

service('auth')->routes($routes, ['only' => ['login', 'logout']]);

$routes->group('admin', ['filter' => 'permission:portal.access'], static function (RouteCollection $routes): void {
    $routes->get('/', 'DashboardController::index', ['as' => 'dashboard']);
    $routes->get('account', 'Admin\\AccountController::index', ['as' => 'admin.account']);
    $routes->post('account/password', 'Admin\\AccountController::updatePassword', ['as' => 'admin.account.password']);
});

$routes->group('admin/admissions', ['filter' => 'permission:admissions.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'AdmissionsController::index', ['as' => 'admissions.index']);
    $routes->post('filter', 'AdmissionsController::filter', ['as' => 'admissions.filter']);
    $routes->get('(:num)', 'AdmissionsController::show/$1', ['as' => 'admissions.show']);
});

$routes->group('admin/admissions', ['filter' => 'permission:admissions.import'], static function (RouteCollection $routes): void {
    $routes->get('import', 'AdmissionImportsController::create', ['as' => 'admissions.import']);
    $routes->post('import', 'AdmissionImportsController::store', ['as' => 'admissions.import.store']);
    $routes->get('import/progress/(:segment)', 'AdmissionImportsController::progress/$1', ['as' => 'admissions.import.progress']);
    $routes->post('logs/(:num)/confirm', 'AdmissionImportsController::confirm/$1', ['as' => 'admissions.logs.confirm']);
    $routes->post('logs/(:num)/retry', 'AdmissionImportsController::retry/$1', ['as' => 'admissions.logs.retry']);
    $routes->post('logs/(:num)/cancel', 'AdmissionImportsController::cancel/$1', ['as' => 'admissions.logs.cancel']);
});

$routes->group('admin/admissions/logs', ['filter' => 'permission:imports.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'AdmissionImportsController::index', ['as' => 'admissions.logs']);
    $routes->get('(:num)', 'AdmissionImportsController::show/$1', ['as' => 'admissions.logs.show']);
    $routes->get('(:num)/rejected', 'AdmissionImportsController::rejected/$1', ['as' => 'admissions.logs.rejected']);
});

$routes->post('admin/admissions/logs/(:num)/validate', 'AdmissionImportsController::workflow/$1/validate', [
    'as' => 'admissions.logs.validate', 'filter' => 'permission:imports.review',
]);
$routes->post('admin/admissions/logs/(:num)/approve', 'AdmissionImportsController::workflow/$1/approve', [
    'as' => 'admissions.logs.approve', 'filter' => 'permission:imports.approve',
]);
$routes->post('admin/admissions/logs/(:num)/publish', 'AdmissionImportsController::workflow/$1/publish', [
    'as' => 'admissions.logs.publish', 'filter' => 'permission:imports.publish',
]);

$routes->get('admin/api-documentation', 'ApiDocumentationController::index', [
    'as' => 'api.documentation',
    'filter' => 'permission:api.docs',
]);
$routes->post('admin/api-deliveries/reset', 'ApiDocumentationController::resetDeliveries', [
    'as' => 'api.deliveries.reset',
    'filter' => 'permission:api.deliveries.reset',
]);
$routes->post('admin/api-deliveries/report', 'ApiDocumentationController::deliveryReport', [
    'as' => 'api.deliveries.report',
    'filter' => 'permission:api.deliveries.report',
]);

$routes->group('admin/examinations', ['filter' => 'permission:examinations.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ExaminationsController::index', ['as' => 'examinations.index']);
    $routes->post('filter', 'ExaminationsController::filter', ['as' => 'examinations.filter']);
    $routes->get('(:num)', 'ExaminationsController::show/$1', ['as' => 'examinations.show']);
});

$routes->group('admin/examinations', ['filter' => 'permission:examinations.import'], static function (RouteCollection $routes): void {
    $routes->get('import', 'ExaminationImportsController::create', ['as' => 'examinations.import']);
    $routes->post('import', 'ExaminationImportsController::store', ['as' => 'examinations.import.store']);
    $routes->get('import/progress/(:segment)', 'ExaminationImportsController::progress/$1', ['as' => 'examinations.import.progress']);
    $routes->post('logs/(:num)/confirm', 'ExaminationImportsController::confirm/$1', ['as' => 'examinations.logs.confirm']);
    $routes->post('logs/(:num)/retry', 'ExaminationImportsController::retry/$1', ['as' => 'examinations.logs.retry']);
    $routes->post('logs/(:num)/cancel', 'ExaminationImportsController::cancel/$1', ['as' => 'examinations.logs.cancel']);
});

$routes->group('admin/examinations/logs', ['filter' => 'permission:examination_imports.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ExaminationImportsController::index', ['as' => 'examinations.logs']);
    $routes->get('(:num)', 'ExaminationImportsController::show/$1', ['as' => 'examinations.logs.show']);
    $routes->get('(:num)/rejected', 'ExaminationImportsController::rejected/$1', ['as' => 'examinations.logs.rejected']);
});

$routes->post('admin/examinations/logs/(:num)/validate', 'ExaminationImportsController::workflow/$1/validate', [
    'as' => 'examinations.logs.validate', 'filter' => 'permission:examination_imports.review',
]);
$routes->post('admin/examinations/logs/(:num)/approve', 'ExaminationImportsController::workflow/$1/approve', [
    'as' => 'examinations.logs.approve', 'filter' => 'permission:examination_imports.approve',
]);
$routes->post('admin/examinations/logs/(:num)/publish', 'ExaminationImportsController::workflow/$1/publish', [
    'as' => 'examinations.logs.publish', 'filter' => 'permission:examination_imports.publish',
]);

$routes->get('admin/examination-api-documentation', 'ExaminationApiDocumentationController::index', [
    'as' => 'examination.api.documentation',
    'filter' => 'permission:examination.api.docs',
]);
$routes->post('admin/examination-api-deliveries/reset', 'ExaminationApiDocumentationController::resetDeliveries', [
    'as' => 'examination.api.deliveries.reset',
    'filter' => 'permission:examination.api.deliveries.reset',
]);
$routes->post('admin/examination-api-deliveries/report', 'ExaminationApiDocumentationController::deliveryReport', [
    'as' => 'examination.api.deliveries.report',
    'filter' => 'permission:examination.api.deliveries.report',
]);

$routes->group('admin/intake', ['filter' => 'permission:intake.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'IntakeController::index', ['as' => 'intake.index']);
    $routes->post('filter', 'IntakeController::filter', ['as' => 'intake.filter']);
    $routes->get('(:num)', 'IntakeController::show/$1', ['as' => 'intake.show']);
});

$routes->group('admin/intake/admission-summary', ['filter' => 'permission:intake.admissions.report'], static function (RouteCollection $routes): void {
    $routes->get('/', 'AdmissionIntakeReportController::index', ['as' => 'intake.admission_summary']);
    $routes->post('filter', 'AdmissionIntakeReportController::filter', ['as' => 'intake.admission_summary.filter']);
});

$routes->group('admin/intake', ['filter' => 'permission:intake.import'], static function (RouteCollection $routes): void {
    $routes->get('import', 'IntakeImportsController::create', ['as' => 'intake.import']);
    $routes->post('import', 'IntakeImportsController::store', ['as' => 'intake.import.store']);
    $routes->get('import/progress/(:segment)', 'IntakeImportsController::progress/$1', ['as' => 'intake.import.progress']);
    $routes->post('logs/(:num)/confirm', 'IntakeImportsController::confirm/$1', ['as' => 'intake.logs.confirm']);
    $routes->post('logs/(:num)/retry', 'IntakeImportsController::retry/$1', ['as' => 'intake.logs.retry']);
    $routes->post('logs/(:num)/cancel', 'IntakeImportsController::cancel/$1', ['as' => 'intake.logs.cancel']);
});

$routes->group('admin/intake/logs', ['filter' => 'permission:intake_imports.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'IntakeImportsController::index', ['as' => 'intake.logs']);
    $routes->get('(:num)', 'IntakeImportsController::show/$1', ['as' => 'intake.logs.show']);
    $routes->get('(:num)/rejected', 'IntakeImportsController::rejected/$1', ['as' => 'intake.logs.rejected']);
});

$routes->post('admin/intake/logs/(:num)/validate', 'IntakeImportsController::workflow/$1/validate', [
    'as' => 'intake.logs.validate', 'filter' => 'permission:intake_imports.review',
]);
$routes->post('admin/intake/logs/(:num)/approve', 'IntakeImportsController::workflow/$1/approve', [
    'as' => 'intake.logs.approve', 'filter' => 'permission:intake_imports.approve',
]);
$routes->post('admin/intake/logs/(:num)/publish', 'IntakeImportsController::workflow/$1/publish', [
    'as' => 'intake.logs.publish', 'filter' => 'permission:intake_imports.publish',
]);

$routes->get('admin/intake-api-documentation', 'IntakeApiDocumentationController::index', [
    'as' => 'intake.api.documentation',
    'filter' => 'permission:intake.api.docs',
]);
$routes->post('admin/intake-api-deliveries/reset', 'IntakeApiDocumentationController::resetDeliveries', [
    'as' => 'intake.api.deliveries.reset',
    'filter' => 'permission:intake.api.deliveries.reset',
]);
$routes->post('admin/intake-api-deliveries/report', 'IntakeApiDocumentationController::deliveryReport', [
    'as' => 'intake.api.deliveries.report',
    'filter' => 'permission:intake.api.deliveries.report',
]);

$routes->group('admin/placements', ['filter' => 'permission:placements.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PlacementController::index', ['as' => 'placements.index']);
    $routes->post('filter', 'PlacementController::filter', ['as' => 'placements.filter']);
    $routes->get('(:num)', 'PlacementController::show/$1', ['as' => 'placements.show']);
});

$routes->group('admin/placements', ['filter' => 'permission:placements.import'], static function (RouteCollection $routes): void {
    $routes->get('import', 'PlacementImportsController::create', ['as' => 'placements.import']);
    $routes->post('import', 'PlacementImportsController::store', ['as' => 'placements.import.store']);
    $routes->get('import/progress/(:segment)', 'PlacementImportsController::progress/$1', ['as' => 'placements.import.progress']);
    $routes->post('logs/(:num)/confirm', 'PlacementImportsController::confirm/$1', ['as' => 'placements.logs.confirm']);
    $routes->post('logs/(:num)/retry', 'PlacementImportsController::retry/$1', ['as' => 'placements.logs.retry']);
    $routes->post('logs/(:num)/cancel', 'PlacementImportsController::cancel/$1', ['as' => 'placements.logs.cancel']);
});

$routes->group('admin/placements/logs', ['filter' => 'permission:placement_imports.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'PlacementImportsController::index', ['as' => 'placements.logs']);
    $routes->get('(:num)', 'PlacementImportsController::show/$1', ['as' => 'placements.logs.show']);
    $routes->get('(:num)/rejected', 'PlacementImportsController::rejected/$1', ['as' => 'placements.logs.rejected']);
});

$routes->post('admin/placements/logs/(:num)/validate', 'PlacementImportsController::workflow/$1/validate', [
    'as' => 'placements.logs.validate', 'filter' => 'permission:placement_imports.review',
]);
$routes->post('admin/placements/logs/(:num)/approve', 'PlacementImportsController::workflow/$1/approve', [
    'as' => 'placements.logs.approve', 'filter' => 'permission:placement_imports.approve',
]);
$routes->post('admin/placements/logs/(:num)/publish', 'PlacementImportsController::workflow/$1/publish', [
    'as' => 'placements.logs.publish', 'filter' => 'permission:placement_imports.publish',
]);

$routes->get('admin/placement-api-documentation', 'PlacementApiDocumentationController::index', [
    'as' => 'placements.api.documentation',
    'filter' => 'permission:placements.api.docs',
]);
$routes->post('admin/placement-api-deliveries/reset', 'PlacementApiDocumentationController::resetDeliveries', [
    'as' => 'placements.api.deliveries.reset',
    'filter' => 'permission:placements.api.deliveries.reset',
]);
$routes->post('admin/placement-api-deliveries/report', 'PlacementApiDocumentationController::deliveryReport', [
    'as' => 'placements.api.deliveries.report',
    'filter' => 'permission:placements.api.deliveries.report',
]);

$routes->group('admin/expenses', ['filter' => 'permission:expenses.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ExpenseController::index', ['as' => 'expenses.index']);
    $routes->post('filter', 'ExpenseController::filter', ['as' => 'expenses.filter']);
    $routes->get('(:num)', 'ExpenseController::show/$1', ['as' => 'expenses.show']);
});

$routes->group('admin/expenses', ['filter' => 'permission:expenses.import'], static function (RouteCollection $routes): void {
    $routes->get('import', 'ExpenseImportsController::create', ['as' => 'expenses.import']);
    $routes->post('import', 'ExpenseImportsController::store', ['as' => 'expenses.import.store']);
    $routes->get('import/progress/(:segment)', 'ExpenseImportsController::progress/$1', ['as' => 'expenses.import.progress']);
    $routes->post('logs/(:num)/confirm', 'ExpenseImportsController::confirm/$1', ['as' => 'expenses.logs.confirm']);
    $routes->post('logs/(:num)/retry', 'ExpenseImportsController::retry/$1', ['as' => 'expenses.logs.retry']);
    $routes->post('logs/(:num)/cancel', 'ExpenseImportsController::cancel/$1', ['as' => 'expenses.logs.cancel']);
});

$routes->group('admin/expenses/logs', ['filter' => 'permission:expense_imports.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ExpenseImportsController::index', ['as' => 'expenses.logs']);
    $routes->get('(:num)', 'ExpenseImportsController::show/$1', ['as' => 'expenses.logs.show']);
    $routes->get('(:num)/rejected', 'ExpenseImportsController::rejected/$1', ['as' => 'expenses.logs.rejected']);
});

$routes->post('admin/expenses/logs/(:num)/validate', 'ExpenseImportsController::workflow/$1/validate', [
    'as' => 'expenses.logs.validate', 'filter' => 'permission:expense_imports.review',
]);
$routes->post('admin/expenses/logs/(:num)/approve', 'ExpenseImportsController::workflow/$1/approve', [
    'as' => 'expenses.logs.approve', 'filter' => 'permission:expense_imports.approve',
]);
$routes->post('admin/expenses/logs/(:num)/publish', 'ExpenseImportsController::workflow/$1/publish', [
    'as' => 'expenses.logs.publish', 'filter' => 'permission:expense_imports.publish',
]);

$routes->get('admin/expense-api-documentation', 'ExpenseApiDocumentationController::index', [
    'as' => 'expenses.api.documentation',
    'filter' => 'permission:expenses.api.docs',
]);
$routes->post('admin/expense-api-deliveries/reset', 'ExpenseApiDocumentationController::resetDeliveries', [
    'as' => 'expenses.api.deliveries.reset',
    'filter' => 'permission:expenses.api.deliveries.reset',
]);
$routes->post('admin/expense-api-deliveries/report', 'ExpenseApiDocumentationController::deliveryReport', [
    'as' => 'expenses.api.deliveries.report',
    'filter' => 'permission:expenses.api.deliveries.report',
]);

$routes->group('admin/results', ['filter' => 'permission:results.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ResultController::index', ['as' => 'results.index']);
    $routes->post('filter', 'ResultController::filter', ['as' => 'results.filter']);
    $routes->get('(:num)', 'ResultController::show/$1', ['as' => 'results.show']);
});

$routes->group('admin/results', ['filter' => 'permission:results.import'], static function (RouteCollection $routes): void {
    $routes->get('import', 'ResultImportsController::create', ['as' => 'results.import']);
    $routes->post('import', 'ResultImportsController::store', ['as' => 'results.import.store']);
    $routes->get('import/progress/(:segment)', 'ResultImportsController::progress/$1', ['as' => 'results.import.progress']);
    $routes->post('logs/(:num)/confirm', 'ResultImportsController::confirm/$1', ['as' => 'results.logs.confirm']);
    $routes->post('logs/(:num)/retry', 'ResultImportsController::retry/$1', ['as' => 'results.logs.retry']);
    $routes->post('logs/(:num)/cancel', 'ResultImportsController::cancel/$1', ['as' => 'results.logs.cancel']);
});

$routes->group('admin/results/logs', ['filter' => 'permission:result_imports.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ResultImportsController::index', ['as' => 'results.logs']);
    $routes->get('(:num)', 'ResultImportsController::show/$1', ['as' => 'results.logs.show']);
    $routes->get('(:num)/rejected', 'ResultImportsController::rejected/$1', ['as' => 'results.logs.rejected']);
});

$routes->post('admin/results/logs/(:num)/validate', 'ResultImportsController::workflow/$1/validate', [
    'as' => 'results.logs.validate', 'filter' => 'permission:result_imports.review',
]);
$routes->post('admin/results/logs/(:num)/approve', 'ResultImportsController::workflow/$1/approve', [
    'as' => 'results.logs.approve', 'filter' => 'permission:result_imports.approve',
]);
$routes->post('admin/results/logs/(:num)/publish', 'ResultImportsController::workflow/$1/publish', [
    'as' => 'results.logs.publish', 'filter' => 'permission:result_imports.publish',
]);

$routes->get('admin/result-api-documentation', 'ResultApiDocumentationController::index', [
    'as' => 'results.api.documentation',
    'filter' => 'permission:results.api.docs',
]);
$routes->post('admin/result-api-deliveries/reset', 'ResultApiDocumentationController::resetDeliveries', [
    'as' => 'results.api.deliveries.reset',
    'filter' => 'permission:results.api.deliveries.reset',
]);
$routes->post('admin/result-api-deliveries/report', 'ResultApiDocumentationController::deliveryReport', [
    'as' => 'results.api.deliveries.report',
    'filter' => 'permission:results.api.deliveries.report',
]);

$routes->group('admin/users', ['namespace' => 'App\\Controllers\\Admin'], static function (RouteCollection $routes): void {
    $routes->get('/', 'UsersController::index', ['as' => 'admin.users', 'filter' => 'permission:users.view']);
    $routes->get('create', 'UsersController::create', ['as' => 'admin.users.create', 'filter' => 'permission:users.create']);
    $routes->post('/', 'UsersController::store', ['as' => 'admin.users.store', 'filter' => 'permission:users.create']);
    $routes->get('(:num)/edit', 'UsersController::edit/$1', ['as' => 'admin.users.edit', 'filter' => 'permission:users.edit']);
    $routes->post('(:num)/role', 'UsersController::updateRole/$1', ['as' => 'admin.users.role', 'filter' => 'permission:roles.assign']);
    $routes->post('(:num)/status', 'UsersController::updateStatus/$1', ['as' => 'admin.users.status', 'filter' => 'permission:users.edit']);
});

$routes->group('admin/roles', ['namespace' => 'App\\Controllers\\Admin', 'filter' => 'permission:roles.view'], static function (RouteCollection $routes): void {
    $routes->get('/', 'RolesController::index', ['as' => 'admin.roles']);
});

$routes->post('api/v1/admissions', 'Api\\V1\\AdmissionsController::index', [
    'as' => 'api.v1.admissions',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/admissions/acknowledge', 'Api\\V1\\AdmissionsController::acknowledge', [
    'as' => 'api.v1.admissions.acknowledge',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/examinations', 'Api\\V1\\ExaminationsController::index', [
    'as' => 'api.v1.examinations',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/examinations/acknowledge', 'Api\\V1\\ExaminationsController::acknowledge', [
    'as' => 'api.v1.examinations.acknowledge',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/intake', 'Api\\V1\\IntakeController::index', [
    'as' => 'api.v1.intake',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/intake/acknowledge', 'Api\\V1\\IntakeController::acknowledge', [
    'as' => 'api.v1.intake.acknowledge',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/placements', 'Api\\V1\\PlacementController::index', [
    'as' => 'api.v1.placements',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/placements/acknowledge', 'Api\\V1\\PlacementController::acknowledge', [
    'as' => 'api.v1.placements.acknowledge',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/expenses', 'Api\\V1\\ExpenseController::index', [
    'as' => 'api.v1.expenses',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/expenses/acknowledge', 'Api\\V1\\ExpenseController::acknowledge', [
    'as' => 'api.v1.expenses.acknowledge',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/results', 'Api\\V1\\ResultController::index', [
    'as' => 'api.v1.results',
    'filter' => 'apiKey',
]);
$routes->post('api/v1/results/acknowledge', 'Api\\V1\\ResultController::acknowledge', [
    'as' => 'api.v1.results.acknowledge',
    'filter' => 'apiKey',
]);
