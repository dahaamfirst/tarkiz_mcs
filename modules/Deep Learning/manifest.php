<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http:// www.gnu.org/licenses/>.
*/

// Basic variables
$name        = 'Deep Learning';
$description = "Enables schools to implement ICHK's Deep Learning approach, in which students choose learning experiences within a multi-day event.";
$entryURL    = "view.php";
$type        = "Additional";
$category    = 'Learn';
$version     = '0.2.01';
$author = "Gibbon Foundation";
$url = "https://gibbonedu.org";

// Module tables & gibbonSettings entries
$moduleTables[] = "CREATE TABLE `deepLearningEvent` (
    `deepLearningEventID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NOT NULL,
    `name` VARCHAR(90) NOT NULL,
    `nameShort` VARCHAR(12) NOT NULL,
    `description` TEXT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `viewableDate` DATETIME NULL,
    `accessOpenDate` DATETIME NULL,
    `accessCloseDate` DATETIME NULL,
    `accessEnrolmentDate` DATETIME NULL,
    `backgroundImage` TEXT NULL,
    `gibbonYearGroupIDList` VARCHAR(255) NOT NULL, 
    PRIMARY KEY (`deepLearningEventID`),
    UNIQUE KEY (`name`, `gibbonSchoolYearID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningEventDate` (
    `deepLearningEventDateID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningEventID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `eventDate` DATE NOT NULL,
    `name` VARCHAR(60) NOT NULL,
    `timeStart` TIME NULL,
    `timeEnd` TIME NULL,
    PRIMARY KEY (`deepLearningEventDateID`),
    UNIQUE KEY (`eventDate`, `deepLearningEventID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningUnit` (
    `deepLearningUnitID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(90) NOT NULL,
    `status` ENUM('Draft','Published','Retired') NOT NULL DEFAULT 'Draft',
    `cost` INT(10) NULL,
    `location` VARCHAR(255) NOT NULL,
    `provider` VARCHAR(255) NOT NULL,
    `majors` VARCHAR(255) NOT NULL,
    `minors` VARCHAR(255) NOT NULL,
    `enrolmentMin` INT(3) NOT NULL,
    `enrolmentMax` INT(3) NOT NULL,
    `headerImage` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `teachersNotes` TEXT NULL,
    `letterToParents` TEXT NULL,
    `riskAssessment` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NULL,
    `gibbonPersonIDCreated` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (`deepLearningUnitID`),
    UNIQUE KEY (`name`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningUnitAuthor` (
    `deepLearningUnitAuthorID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningUnitID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `timestamp` TIMESTAMP NULL,
    PRIMARY KEY (`deepLearningUnitAuthorID`),
    UNIQUE KEY (`gibbonPersonID`, `deepLearningUnitID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningUnitBlock` (
    `deepLearningUnitBlockID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningUnitID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `title` VARCHAR(120) NOT NULL,
    `type` ENUM('Main','Sidebar') NOT NULL DEFAULT 'Main',
    `length` VARCHAR(3) NOT NULL,
    `content` TEXT NULL,
    `sequenceNumber` INT(6) NOT NULL,
    PRIMARY KEY (`deepLearningUnitBlockID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningUnitPhoto` (
    `deepLearningUnitPhotoID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningUnitID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `filePath` VARCHAR(255) NOT NULL,
    `caption` VARCHAR(120) NOT NULL,
    `sequenceNumber` INT(6) NOT NULL,
    PRIMARY KEY (`deepLearningUnitPhotoID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningUnitTag` (
    `deepLearningUnitTagID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `tag` VARCHAR(60) NOT NULL,
    PRIMARY KEY (`deepLearningUnitTagID`),
    UNIQUE KEY (`tag`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningExperience` (
    `deepLearningExperienceID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningEventID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `deepLearningUnitID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `name` VARCHAR(90) NOT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `gibbonGroupID` INT(8) UNSIGNED ZEROFILL NULL,
    `gibbonYearGroupIDList` VARCHAR(255) NOT NULL,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (`deepLearningExperienceID`),
    UNIQUE KEY (`name`, `deepLearningEventID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningExperienceVenue` (
    `deepLearningExperienceVenueID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningExperienceID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `deepLearningEventDateID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `gibbonSpaceID` INT(10) UNSIGNED ZEROFILL DEFAULT NULL,
    `venueExternal` VARCHAR(255) NOT NULL,
    `venueExternalUrl` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `allDay` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timeStart` TIME NULL,
    `timeEnd` TIME NULL,
    PRIMARY KEY (`deepLearningExperienceVenueID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningExperienceTrip` (
    `deepLearningExperienceTripID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningExperienceID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `deepLearningEventDateIDList` VARCHAR(255) NULL,
    `tripPlannerRequestID` INT(7) UNSIGNED ZEROFILL NULL,
    PRIMARY KEY (`deepLearningExperienceTripID`),
    UNIQUE KEY (`deepLearningExperienceID`, `tripPlannerRequestID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningStaff` (
    `deepLearningStaffID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningExperienceID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `canEdit` ENUM('Y','N') NOT NULL DEFAULT 'N', 
    `role` VARCHAR(60) NOT NULL,
    `notes` TEXT NULL,
    `deepLearningEventDateIDList` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`deepLearningStaffID`),
    UNIQUE KEY (`gibbonPersonID`, `deepLearningExperienceID`)
) ENGINE=InnoDB CHARSET=utf8mb3";

$moduleTables[] = "CREATE TABLE `deepLearningEnrolment` (
    `deepLearningEnrolmentID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningExperienceID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `deepLearningEventID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `deepLearningChoiceID` INT(12) UNSIGNED ZEROFILL NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `status` ENUM('Pending','Confirmed') NOT NULL DEFAULT 'Pending',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NULL,
    `gibbonPersonIDCreated` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (`deepLearningEnrolmentID`),
    UNIQUE KEY (`gibbonPersonID`, `deepLearningEventID`)
) ENGINE=InnoDB CHARSET=utf8mb3;";

$moduleTables[] = "CREATE TABLE `deepLearningChoice` (
    `deepLearningChoiceID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `deepLearningExperienceID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `deepLearningEventID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `choice` INT(2) NOT NULL DEFAULT 1,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NULL,
    `gibbonPersonIDCreated` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonIDModified` INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (`deepLearningChoiceID`),
    UNIQUE KEY (`gibbonPersonID`, `choice`, `deepLearningEventID`)
) ENGINE=InnoDB CHARSET=utf8mb3;";

// Add gibbonSettings entries
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Deep Learning', 'welcomeText', 'Welcome Text', 'A short message displayed on the landing page for Deep Learning events.', '')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Deep Learning', 'signUpText', 'Sign Up Text', 'Instructions displayed on the sign up form.', 'Every effort is made to give students their first choice, however space and resources for many trips are limited, so students are asked to make a second and third choice.')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Deep Learning', 'enrolmentMin', 'Default Enrolment Min', 'Unit should not run below this number of students.', '4')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Deep Learning', 'enrolmentMax', 'Default Enrolment Max', 'Enrolment should not exceed this number of students.', '20')";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Deep Learning', 'signUpChoices', 'Sign up Choices', 'How many choices can users make when signing up?', '3')";

// Notification events
$gibbonSetting[] = "INSERT INTO `gibbonNotificationEvent` (`event`, `moduleName`, `actionName`, `type`, `scopes`, `active`) VALUES ('Enrolment Changes', 'Deep Learning', 'Deep Learning Overview_view', 'Additional', 'All,gibbonYearGroupID', 'Y');";

// Action rows
// One array per action

$actionRows[] = [
    'name'                      => 'Manage Events',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => 'Allows the user to manage Deep Learning events.',
    'URLList'                   => 'events_manage.php,events_manage_add.php,events_manage_edit.php,events_manage_delete.php',
    'entryURL'                  => 'events_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];
 
$actionRows[] = [
    'name'                      => 'Manage Experiences_all',
    'precedence'                => '1',
    'category'                  => 'Administration',
    'description'               => 'Allows the user to manage all Deep Learning experiences.',
    'URLList'                   => 'experience_manage.php,experience_manage_add.php,experience_manage_addMultiple.php,experience_manage_delete.php,experience_manage_edit.php',
    'entryURL'                  => 'experience_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Experiences_my',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => 'Allows the user to manage Deep Learning experiences they have been added to as staff.',
    'URLList'                   => 'experience_manage.php,experience_manage_edit.php',
    'entryURL'                  => 'experience_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Units_all',
    'precedence'                => '1',
    'category'                  => 'Administration',
    'description'               => 'Allows the user to manage all Deep Learning units.',
    'URLList'                   => 'unit_manage.php,unit_manage_add.php,unit_manage_delete.php,unit_manage_edit.php',
    'entryURL'                  => 'unit_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Units_my',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => 'Allows the user to manage Deep Learning units they are an author of.',
    'URLList'                   => 'unit_manage.php,unit_manage_add.php,unit_manage_delete.php,unit_manage_edit.php',
    'entryURL'                  => 'unit_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Choices',
    'precedence'                => '0',
    'category'                  => 'Enrolment',
    'description'               => 'Manage sign ups and enrolments for Deep Learning experiences.',
    'URLList'                   => 'choices_manage.php,choices_manage_generate.php,choices_manage_addEdit.php,choices_manage_delete.php',
    'entryURL'                  => 'choices_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage DL Groups',
    'precedence'                => '0',
    'category'                  => 'Enrolment',
    'description'               => 'Manage enrolments for Deep Learning experiences in a drag-drop interface.',
    'URLList'                   => 'enrolment_manage_groups.php',
    'entryURL'                  => 'enrolment_manage_groups.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage DL Staffing',
    'precedence'                => '0',
    'category'                  => 'Enrolment',
    'description'               => 'Manage staffing for Deep Learning experiences in a drag-drop interface.',
    'URLList'                   => 'enrolment_manage_staffing.php',
    'entryURL'                  => 'enrolment_manage_staffing.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Enrolment by Event',
    'precedence'                => '0',
    'category'                  => 'Enrolment',
    'description'               => 'Manage enrolments for Deep Learning experiences.',
    'URLList'                   => 'enrolment_manage_byEvent.php,enrolment_manage_byEvent_edit.php,enrolment_manage_byPerson_addEdit.php,enrolment_manage_byPerson_delete.php',
    'entryURL'                  => 'enrolment_manage_byEvent.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Enrolment by Person',
    'precedence'                => '0',
    'category'                  => 'Enrolment',
    'description'               => 'Manage enrolments for Deep Learning experiences.',
    'URLList'                   => 'enrolment_manage_byPerson.php,enrolment_manage_byPerson_addEdit.php,enrolment_manage_byPerson_delete.php',
    'entryURL'                  => 'enrolment_manage_byPerson.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Settings',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => 'Allows the user change Deep Learning settings.',
    'URLList'                   => 'settings.php',
    'entryURL'                  => 'settings.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Deep Learning Events_view',
    'precedence'                => '0',
    'category'                  => 'Explore',
    'description'               => 'Enables users to view active Deep Learning events.',
    'URLList'                   => 'view.php,view_event.php,view_experience.php',
    'entryURL'                  => 'view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Deep Learning Events_viewInactive',
    'precedence'                => '1',
    'category'                  => 'Explore',
    'description'               => 'Enables users to view active and inactive Deep Learning events.',
    'URLList'                   => 'view.php,view_event.php,view_experience.php',
    'entryURL'                  => 'view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Deep Learning Events_signUp',
    'precedence'                => '2',
    'category'                  => 'Explore',
    'description'               => 'Enables users to view and sign up for active Deep Learning events.',
    'URLList'                   => 'view.php,view_event.php,view_experience.php,view_experience_signUp.php',
    'entryURL'                  => 'view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'My Deep Learning',
    'precedence'                => '0',
    'category'                  => 'Explore',
    'description'               => 'Enables users to view their enrolled Deep Learning events.',
    'URLList'                   => 'viewMyDL.php',
    'entryURL'                  => 'viewMyDL.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Deep Learning_myChildren',
    'precedence'                => '0',
    'category'                  => 'Explore',
    'description'               => 'Enables parents to view enrolled Deep Learning events for their children.',
    'URLList'                   => 'viewDL.php,viewMyDL.php',
    'entryURL'                  => 'viewDL.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'N',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Deep Learning Overview_view',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View an overview of all active Deep Learning experiences.',
    'URLList'                   => 'report_overview.php,report_overview_editStatus.php',
    'entryURL'                  => 'report_overview.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Deep Learning Overview_editAnyStatus',
    'precedence'                => '1',
    'category'                  => 'Reports',
    'description'               => 'View an overview of all active Deep Learning experiences and edit the status of students.',
    'URLList'                   => 'report_overview.php,report_overview_editStatus.php',
    'entryURL'                  => 'report_overview.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Students Not Signed Up',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View a report of students not signed up for an event.',
    'URLList'                   => 'report_notSignedUp.php',
    'entryURL'                  => 'report_notSignedUp.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Students Not Enrolled',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View a report of students who have signed up but have not been enrolled in an experience.',
    'URLList'                   => 'report_notEnrolled.php',
    'entryURL'                  => 'report_notEnrolled.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View DL Staffing',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View a report of staff assigned to an experience.',
    'URLList'                   => 'report_staffing.php',
    'entryURL'                  => 'report_staffing.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Unassigned Staff',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View a report of staff not assigned to an experience.',
    'URLList'                   => 'report_unassigned.php',
    'entryURL'                  => 'report_unassigned.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Student Choices',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View a list of choices students have made for a DL event.',
    'URLList'                   => 'report_choices.php',
    'entryURL'                  => 'report_choices.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Student Attendance by Group',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => 'View student attendance for a DL event.',
    'URLList'                   => 'report_attendance.php',
    'entryURL'                  => 'report_attendance.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];


// Hooks
// $hooks[] = ''; // Serialised array to create hook and set options. See Hooks documentation online.

$array = [
    'sourceModuleName' => 'Deep Learning',
    'sourceModuleAction' => 'Deep Learning Events_view',
    'sourceModuleInclude' => 'hook_studentProfile_deepLearning.php',
];
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Deep Learning', 'Student Profile', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='Deep Learning'));";
