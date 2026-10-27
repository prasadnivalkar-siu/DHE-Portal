<?php

namespace Tests\Unit;

use Config\AuthGroups;
use PHPUnit\Framework\TestCase;

final class AuthGroupsTest extends TestCase
{
    private AuthGroups $config;

    protected function setUp(): void
    {
        $this->config = new AuthGroups();
    }

    public function testDefinesExpectedRolesAndSafeDefault(): void
    {
        self::assertSame('viewer', $this->config->defaultGroup);
        self::assertSame(
            ['superadmin', 'admin', 'data_manager', 'auditor', 'viewer'],
            array_keys($this->config->groups),
        );
    }

    public function testViewerCannotMutateDataOrManageUsers(): void
    {
        $permissions = $this->config->matrix['viewer'];

        self::assertContains('admissions.view', $permissions);
        self::assertContains('intake.view', $permissions);
        self::assertContains('intake.api.docs', $permissions);
        self::assertContains('placements.view', $permissions);
        self::assertContains('placements.api.docs', $permissions);
        self::assertContains('expenses.view', $permissions);
        self::assertContains('expenses.api.docs', $permissions);
        self::assertContains('results.view', $permissions);
        self::assertContains('results.api.docs', $permissions);
        self::assertNotContains('admissions.import', $permissions);
        self::assertNotContains('intake.import', $permissions);
        self::assertNotContains('intake.api.deliveries.reset', $permissions);
        self::assertNotContains('placements.import', $permissions);
        self::assertNotContains('placements.api.deliveries.reset', $permissions);
        self::assertNotContains('expenses.import', $permissions);
        self::assertNotContains('expenses.api.deliveries.reset', $permissions);
        self::assertNotContains('results.import', $permissions);
        self::assertNotContains('results.api.deliveries.reset', $permissions);
        self::assertNotContains('users.view', $permissions);
        self::assertNotContains('roles.assign', $permissions);
        self::assertNotContains('api.deliveries.reset', $permissions);
    }

    public function testDataManagerCanImportWithoutUserAdministration(): void
    {
        $permissions = $this->config->matrix['data_manager'];

        self::assertContains('admissions.import', $permissions);
        self::assertContains('imports.view', $permissions);
        self::assertContains('intake.import', $permissions);
        self::assertContains('intake_imports.view', $permissions);
        self::assertContains('intake.api.deliveries.report', $permissions);
        self::assertContains('placements.import', $permissions);
        self::assertContains('placement_imports.view', $permissions);
        self::assertContains('placements.api.deliveries.report', $permissions);
        self::assertContains('expenses.import', $permissions);
        self::assertContains('expense_imports.view', $permissions);
        self::assertContains('expenses.api.deliveries.report', $permissions);
        self::assertContains('results.import', $permissions);
        self::assertContains('result_imports.view', $permissions);
        self::assertContains('results.api.deliveries.report', $permissions);
        self::assertNotContains('users.view', $permissions);
        self::assertNotContains('roles.assign', $permissions);
    }

    public function testAdministrativePermissionsAreDeclared(): void
    {
        foreach (['users.view', 'users.create', 'users.edit', 'roles.view', 'roles.assign', 'imports.review', 'imports.approve', 'imports.publish', 'api.deliveries.reset', 'api.deliveries.report', 'intake.view', 'intake.import', 'intake_imports.view', 'intake_imports.review', 'intake_imports.approve', 'intake_imports.publish', 'intake.api.docs', 'intake.api.deliveries.reset', 'intake.api.deliveries.report', 'placements.view', 'placements.import', 'placement_imports.view', 'placement_imports.review', 'placement_imports.approve', 'placement_imports.publish', 'placements.api.docs', 'placements.api.deliveries.reset', 'placements.api.deliveries.report', 'expenses.view', 'expenses.import', 'expense_imports.view', 'expense_imports.review', 'expense_imports.approve', 'expense_imports.publish', 'expenses.api.docs', 'expenses.api.deliveries.reset', 'expenses.api.deliveries.report', 'results.view', 'results.import', 'result_imports.view', 'result_imports.review', 'result_imports.approve', 'result_imports.publish', 'results.api.docs', 'results.api.deliveries.reset', 'results.api.deliveries.report'] as $permission) {
            self::assertArrayHasKey($permission, $this->config->permissions);
        }

        self::assertContains('api.deliveries.reset', $this->config->matrix['admin']);
        self::assertContains('imports.review', $this->config->matrix['auditor']);
        self::assertContains('api.deliveries.report', $this->config->matrix['auditor']);
        self::assertContains('intake_imports.review', $this->config->matrix['auditor']);
        self::assertContains('placement_imports.review', $this->config->matrix['auditor']);
        self::assertContains('expense_imports.review', $this->config->matrix['auditor']);
        self::assertContains('result_imports.review', $this->config->matrix['auditor']);
    }
}
