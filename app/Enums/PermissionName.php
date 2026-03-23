<?php

namespace App\Enums;

enum PermissionName: string
{
    case ViewAdminDashboard = 'dashboard.admin.view';
    case ViewAcademicSetup = 'academic.setup.view';
    case ManageAcademicSetup = 'academic.setup.manage';
    case ViewUserManagement = 'admin.user-management.view';
    case ManageUsers = 'admin.users.manage';
    case ManageTeacherLoads = 'admin.teacher-loads.manage';
    case ViewSf1Imports = 'sf1.imports.view';
    case ManageSf1Imports = 'sf1.imports.manage';
    case ViewTemplateManagement = 'templates.view';
    case ManageTemplates = 'templates.manage';
    case ViewSubmissionMonitoring = 'submission.monitoring.view';
    case ManageQuarterLocks = 'submission.monitoring.lock';
    case ViewAuditLogs = 'audit-logs.view';
    case ViewTeacherDashboard = 'dashboard.teacher.view';
    case ViewTeacherLoads = 'teacher.loads.view';
    case ViewTeacherGradeEntry = 'teacher.grades.view';
    case ViewTeacherGradingSheetExports = 'teacher.grading-sheet.view';
    case ExportTeacherGradingSheets = 'teacher.grading-sheet.export';
    case ViewTeacherReturnedSubmissions = 'teacher.returned-submissions.view';
    case ViewAdviserDashboard = 'dashboard.adviser.view';
    case ViewAdvisorySections = 'adviser.sections.view';
    case ManageAdvisoryReviews = 'adviser.reviews.manage';
    case ViewRegistrarDashboard = 'dashboard.registrar.view';
    case ViewRegistrarRecords = 'registrar.records.view';
}
