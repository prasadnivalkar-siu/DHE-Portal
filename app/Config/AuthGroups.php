<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'viewer';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Super Administrator',
            'description' => 'Complete portal, user, and role administration.',
        ],
        'admin' => [
            'title'       => 'Administrator',
            'description' => 'Manages portal data and non-super-administrator users.',
        ],
        'data_manager' => [
            'title'       => 'Data Manager',
            'description' => 'Imports and maintains portal datasets.',
        ],
        'auditor' => [
            'title'       => 'Auditor',
            'description' => 'Reviews portal data and import audit logs.',
        ],
        'viewer' => [
            'title'       => 'Viewer',
            'description' => 'Read-only access to published portal data and API documentation.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'portal.access'      => 'Access the DHE administration portal',
        'admissions.view'    => 'View admission records',
        'admissions.import'  => 'Import admission workbooks',
        'imports.view'       => 'View import history and row errors',
        'imports.review'     => 'Validate imported admission datasets',
        'imports.approve'    => 'Approve validated admission datasets',
        'imports.publish'    => 'Publish approved admission datasets',
        'api.docs'           => 'View API documentation',
        'api.deliveries.reset' => 'Purge or reset API delivery tracking',
        'api.deliveries.report' => 'Download API delivery reports',
        'examinations.view' => 'View examination records',
        'examinations.import' => 'Import examination workbooks',
        'examination_imports.view' => 'View examination import history and row errors',
        'examination_imports.review' => 'Validate imported examination datasets',
        'examination_imports.approve' => 'Approve validated examination datasets',
        'examination_imports.publish' => 'Publish approved examination datasets',
        'examination.api.docs' => 'View examination API documentation',
        'examination.api.deliveries.reset' => 'Purge or reset examination API delivery tracking',
        'examination.api.deliveries.report' => 'Download examination API delivery reports',
        'intake.view' => 'View intake records',
        'intake.admissions.report' => 'View intake versus admissions summary',
        'intake.import' => 'Import intake workbooks',
        'intake_imports.view' => 'View intake import history and row errors',
        'intake_imports.review' => 'Validate imported intake datasets',
        'intake_imports.approve' => 'Approve validated intake datasets',
        'intake_imports.publish' => 'Publish approved intake datasets',
        'intake.api.docs' => 'View intake API documentation',
        'intake.api.deliveries.reset' => 'Purge or reset intake API delivery tracking',
        'intake.api.deliveries.report' => 'Download intake API delivery reports',
        'placements.view' => 'View placement records',
        'placements.import' => 'Import placement workbooks',
        'placement_imports.view' => 'View placement import history and row errors',
        'placement_imports.review' => 'Validate imported placement datasets',
        'placement_imports.approve' => 'Approve validated placement datasets',
        'placement_imports.publish' => 'Publish approved placement datasets',
        'placements.api.docs' => 'View placement API documentation',
        'placements.api.deliveries.reset' => 'Purge or reset placement API delivery tracking',
        'placements.api.deliveries.report' => 'Download placement API delivery reports',
        'expenses.view' => 'View expense records',
        'expenses.import' => 'Import expense workbooks',
        'expense_imports.view' => 'View expense import history and row errors',
        'expense_imports.review' => 'Validate imported expense datasets',
        'expense_imports.approve' => 'Approve validated expense datasets',
        'expense_imports.publish' => 'Publish approved expense datasets',
        'expenses.api.docs' => 'View expense API documentation',
        'expenses.api.deliveries.reset' => 'Purge or reset expense API delivery tracking',
        'expenses.api.deliveries.report' => 'Download expense API delivery reports',
        'results.view' => 'View result records',
        'results.import' => 'Import result workbooks',
        'result_imports.view' => 'View result import history and row errors',
        'result_imports.review' => 'Validate imported result datasets',
        'result_imports.approve' => 'Approve validated result datasets',
        'result_imports.publish' => 'Publish approved result datasets',
        'results.api.docs' => 'View result API documentation',
        'results.api.deliveries.reset' => 'Purge or reset result API delivery tracking',
        'results.api.deliveries.report' => 'Download result API delivery reports',
        'users.view'         => 'View portal users',
        'users.create'       => 'Create portal users',
        'users.edit'         => 'Activate or deactivate portal users',
        'roles.view'         => 'View roles and permissions',
        'roles.assign'       => 'Assign roles to portal users',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'portal.*',
            'admissions.*',
            'imports.*',
            'api.*',
            'examinations.*',
            'examination_imports.*',
            'examination.api.*',
            'intake.*',
            'intake_imports.*',
            'placements.*',
            'placement_imports.*',
            'expenses.*',
            'expense_imports.*',
            'results.*',
            'result_imports.*',
            'users.*',
            'roles.*',
        ],
        'admin' => [
            'portal.access',
            'admissions.*',
            'imports.*',
            'api.docs',
            'api.deliveries.reset',
            'api.deliveries.report',
            'examinations.*',
            'examination_imports.*',
            'examination.api.*',
            'intake.*',
            'intake_imports.*',
            'placements.*',
            'placement_imports.*',
            'expenses.*',
            'expense_imports.*',
            'results.*',
            'result_imports.*',
            'users.view',
            'users.create',
            'users.edit',
            'roles.view',
            'roles.assign',
        ],
        'data_manager' => [
            'portal.access',
            'admissions.view',
            'admissions.import',
            'imports.view',
            'api.docs',
            'api.deliveries.report',
            'examinations.view',
            'examinations.import',
            'examination_imports.view',
            'examination.api.docs',
            'examination.api.deliveries.report',
            'intake.view',
            'intake.admissions.report',
            'intake.import',
            'intake_imports.view',
            'intake.api.docs',
            'intake.api.deliveries.report',
            'placements.view',
            'placements.import',
            'placement_imports.view',
            'placements.api.docs',
            'placements.api.deliveries.report',
            'expenses.view',
            'expenses.import',
            'expense_imports.view',
            'expenses.api.docs',
            'expenses.api.deliveries.report',
            'results.view',
            'results.import',
            'result_imports.view',
            'results.api.docs',
            'results.api.deliveries.report',
        ],
        'auditor' => [
            'portal.access',
            'admissions.view',
            'imports.view',
            'imports.review',
            'api.docs',
            'api.deliveries.report',
            'examinations.view',
            'examination_imports.view',
            'examination_imports.review',
            'examination.api.docs',
            'examination.api.deliveries.report',
            'intake.view',
            'intake.admissions.report',
            'intake_imports.view',
            'intake_imports.review',
            'intake.api.docs',
            'intake.api.deliveries.report',
            'placements.view',
            'placement_imports.view',
            'placement_imports.review',
            'placements.api.docs',
            'placements.api.deliveries.report',
            'expenses.view',
            'expense_imports.view',
            'expense_imports.review',
            'expenses.api.docs',
            'expenses.api.deliveries.report',
            'results.view',
            'result_imports.view',
            'result_imports.review',
            'results.api.docs',
            'results.api.deliveries.report',
        ],
        'viewer' => [
            'portal.access',
            'admissions.view',
            'api.docs',
            'examinations.view',
            'examination.api.docs',
            'intake.view',
            'intake.admissions.report',
            'intake.api.docs',
            'placements.view',
            'placements.api.docs',
            'expenses.view',
            'expenses.api.docs',
            'results.view',
            'results.api.docs',
        ],
    ];
}
