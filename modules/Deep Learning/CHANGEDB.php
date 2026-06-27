<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v0.0.01
$sql[$count][0] = "0.0.01";
$sql[$count][1] = "";

// v0.0.02
$sql[$count][0] = "0.0.02";
$sql[$count][1] = "ALTER TABLE `deepLearningUnit` ADD `enrolmentMin` INT(3) NOT NULL AFTER `minors`;end
ALTER TABLE `deepLearningUnit` ADD `enrolmentMax` INT(3) NOT NULL AFTER `enrolmentMin`;end
ALTER TABLE `deepLearningExperience` DROP `cost`, DROP `location`, DROP `provider`;end
ALTER TABLE `deepLearningExperience` DROP `enrolmentMin`, DROP `enrolmentMax`;end
ALTER TABLE `deepLearningUnitBlock` CHANGE `type` `type` ENUM('Main','Sidebar') NOT NULL DEFAULT 'Main';end
ALTER TABLE `deepLearningExperienceTrip` ADD `deepLearningEventDateID` INT(12) UNSIGNED ZEROFILL NULL AFTER `deepLearningExperienceID`;end
ALTER TABLE `deepLearningUnitBlock` DROP `deepLearningEventDateID`;end
";

// v0.0.03
$sql[$count][0] = "0.0.03";
$sql[$count][1] = "ALTER TABLE `deepLearningExperienceTrip` DROP `deepLearningEventDateID`;end
ALTER TABLE `deepLearningExperienceTrip` ADD `deepLearningEventDateIDList` VARCHAR(255) NULL AFTER `deepLearningExperienceID`;end
ALTER TABLE `deepLearningExperienceTrip` ADD UNIQUE(`deepLearningExperienceID`, `tripPlannerRequestID`);end
";

// v0.0.04
$sql[$count][0] = "0.0.04";
$sql[$count][1] = "INSERT INTO `gibbonAction` (`gibbonActionID`, `gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES (NULL, (SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning'), 'View Student Choices', 0, 'Reports', 'View a list of choices students have made for a DL event.', 'report_choices.php','report_choices.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Deep Learning' AND gibbonAction.name='View Student Choices'));end
";

// v0.0.05
$sql[$count][0] = "0.0.05";
$sql[$count][1] = "INSERT INTO `gibbonAction` (`gibbonActionID`, `gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES (NULL, (SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning'), 'View Deep Learning_myChildren', 0, 'Explore', 'Enables parents to view enrolled Deep Learning events for their children.', 'viewDL.php','viewDL.php', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'N', 'Y', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '004', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Deep Learning' AND gibbonAction.name='View Deep Learning_myChildren'));end
";


// v0.0.06
$sql[$count][0] = "0.0.06";
$sql[$count][1] = "UPDATE `gibbonAction` SET name='Deep Learning Overview_view', `URLList`='report_overview.php,report_overview_editStatus.php' WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning') AND name='Deep Learning Overview';end
ALTER TABLE `deepLearningEnrolment` ADD `timestampModified` TIMESTAMP NULL AFTER `timestampCreated`;end
ALTER TABLE `deepLearningEnrolment` ADD `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NOT NULL AFTER `gibbonPersonIDCreated`;end
INSERT INTO `gibbonAction` (`gibbonActionID`, `gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES (NULL, (SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning'), 'Deep Learning Overview_editAnyStatus', 1, 'Reports', 'View an overview of all active Deep Learning experiences and edit the status of students.', 'report_overview.php,report_overview_editStatus.php','report_overview.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Deep Learning' AND gibbonAction.name='Deep Learning Overview_editAnyStatus'));end
INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`) VALUES ('Enrolment Changes', 'Deep Learning', 'Deep Learning Overview_view', 'Additional', 'All,gibbonYearGroupID', 'Y');end
";

// v0.0.07
$sql[$count][0] = "0.0.07";
$sql[$count][1] = "ALTER TABLE `deepLearningUnit` ADD `letterToParents` TEXT NULL AFTER `teachersNotes`;end
ALTER TABLE `deepLearningUnit` ADD `riskAssessment` TEXT NULL AFTER `letterToParents`;end
";

// v0.0.08
$sql[$count][0] = "0.0.08";
$sql[$count][1] = "ALTER TABLE `deepLearningExperienceVenue` ADD `allDay` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `description`;end
";

// v0.0.09
$sql[$count][0] = "0.0.09";
$sql[$count][1] = "INSERT INTO `gibbonAction` (`gibbonActionID`, `gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `entrySidebar`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES (NULL, (SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning'), 'Student Attendance by Group', 0, 'Reports', 'View student attendance for a DL event.', 'report_attendance.php','report_attendance.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Deep Learning' AND gibbonAction.name='Student Attendance by Group'));end
";

// v0.0.10
$sql[$count][0] = "0.0.10";
$sql[$count][1] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Deep Learning', 'Student Profile', 'a:3:{s:16:\"sourceModuleName\";s:13:\"Deep Learning\";s:18:\"sourceModuleAction\";s:25:\"Deep Learning Events_view\";s:19:\"sourceModuleInclude\";s:36:\"hook_studentProfile_deepLearning.php\";}', (SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning'));end
";

// v0.0.11
$sql[$count][0] = "0.0.11";
$sql[$count][1] = "
";

// v0.0.12
$sql[$count][0] = "0.0.12";
$sql[$count][1] = "UPDATE `gibbonAction` SET `URLList`='viewDL.php,viewMyDL.php' WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning') AND name='View Deep Learning_myChildren';end
";

// v0.0.13
$sql[$count][0] = "0.0.13";
$sql[$count][1] = "ALTER TABLE `deepLearningExperience` ADD `gibbonGroupID` INT(8) UNSIGNED ZEROFILL NULL AFTER `active`;end
UPDATE deepLearningExperience SET gibbonGroupID=(SELECT tripPlannerRequests.messengerGroupID FROM deepLearningExperienceTrip JOIN tripPlannerRequests ON (tripPlannerRequests.tripPlannerRequestID=deepLearningExperienceTrip.tripPlannerRequestID) WHERE deepLearningExperienceTrip.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND tripPlannerRequests.messengerGroupID IS NOT NULL ORDER BY deepLearningExperienceTripID LIMIT 1) WHERE deepLearningExperience.gibbonGroupID IS NULL;end
";

//v0.1.00
++$count;
$sql[$count][0] = '0.1.00';
$sql[$count][1] = "
UPDATE gibbonModule SET author='Gibbon Foundation', url='https://gibbonedu.org' WHERE name='Deep Learning';end
";

//v0.1.01
++$count;
$sql[$count][0] = '0.1.01';
$sql[$count][1] = "
";

//v0.2.00
++$count;
$sql[$count][0] = '0.2.00';
$sql[$count][1] = "
";

//v0.2.01
++$count;
$sql[$count][0] = '0.2.01';
$sql[$count][1] = "
";
