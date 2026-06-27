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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Data\Validator;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'search'                   => $_POST['search'] ?? '',
    'deepLearningEventID'      => $_POST['deepLearningEventID'] ?? '',
    'deepLearningExperienceID' => $_POST['deepLearningExperienceID'] ?? '',
    'deepLearningEnrolmentID'  => $_POST['deepLearningEnrolmentID'] ?? '',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/report_overview_editStatus.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_overview_editStatus.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $staffGateway = $container->get(StaffGateway::class);

    $data = [
        'status'                 => $_POST['status'] ?? '',
        'notes'                  => $_POST['notes'] ?? '',
        'timestampModified'      => date('Y-m-d H:i:s'),
        'gibbonPersonIDModified' => $session->get('gibbonPersonID'),
    ];

    // Validate the required values are present
    if (empty($params['deepLearningEnrolmentID']) || empty($data['status'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$enrolmentGateway->exists($params['deepLearningEnrolmentID'])) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Check overall access
    $highestAction = getHighestGroupedAction($guid, '/modules/Deep Learning/report_overview_editStatus.php', $connection2);
    if (empty($highestAction)) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    // Confirm edit access
    $staff = $staffGateway->getStaffExperienceAccess($params['deepLearningExperienceID'], $session->get('gibbonPersonID'));
    if ($highestAction != 'Deep Learning Overview_editAnyStatus' && (empty($staff) || ($staff['role'] != 'Trip Leader' && $staff['canEdit'] != 'Y'))) {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $enrolmentGateway->update($params['deepLearningEnrolmentID'], $data);
    
    $URL .= "&return=success0";
    header("Location: {$URL}");
}
