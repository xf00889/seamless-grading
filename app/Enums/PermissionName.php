<?php

namespace App\Enums;

enum PermissionName: string
{
    case ViewAdminDashboard = 'dashboard.admin.view';
    case ViewAcademicSetup = 'academic.setup.view';
    case ViewTeacherDashboard = 'dashboard.teacher.view';
    case ViewTeacherLoads = 'teacher.loads.view';
    case ViewAdviserDashboard = 'dashboard.adviser.view';
    case ViewAdvisorySections = 'adviser.sections.view';
    case ViewRegistrarDashboard = 'dashboard.registrar.view';
    case ViewRegistrarRecords = 'registrar.records.view';
}
