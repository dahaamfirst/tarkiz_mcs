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
use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceTripGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'deepLearningEventID' => $_POST['deepLearningEventID'] ?? '',
    'sidebar'             => 'false',
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/enrolment_manage_staffing.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_staffing.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $experienceGateway = $container->get(ExperienceGateway::class);
    $staffGateway = $container->get(StaffGateway::class);

    $staffingList = $_POST['person'] ?? [];
    $roleList = $_POST['role'] ?? [];

    if (empty($params['deepLearningEventID']) || empty($staffingList)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $experiences = [];
    $unassigned = [];

    // Update staffing
    foreach ($staffingList as $gibbonPersonID => $deepLearningExperienceID) {
        if (empty($deepLearningExperienceID)) {
            $unassigned[] = $gibbonPersonID;
            continue;
        }

        $staffing = $staffGateway->selectStaffByEventAndPerson($params['deepLearningEventID'], $gibbonPersonID)->fetch();
        $experiences[] = $deepLearningExperienceID;

        if (!empty($staffing)) {
            // Update and existing staffing
            $data = [
                'deepLearningExperienceID' => $deepLearningExperienceID,
                'role'                     => $roleList[$gibbonPersonID] ?? 'Support',
            ];

            $updated = $staffGateway->update($staffing['deepLearningStaffID'], $data);
        } else {
            // Add a new staffing
            $data = [
                'deepLearningExperienceID' => $deepLearningExperienceID,
                'gibbonPersonID'           => $gibbonPersonID,
                'role'                     => $roleList[$gibbonPersonID] ?? 'Support',
                'canEdit'                  => 'N',
            ];

            $inserted = $staffGateway->insert($data);
            $partialFail &= !$inserted;
        }
    }

    // Remove staffing that have been unassigned
    foreach ($unassigned as $gibbonPersonID) {
        $staffGateway->deleteStaffByEvent($params['deepLearningEventID'], $gibbonPersonID);
    }

    // Sync Trip Planner Staff and Messenger Group
    $experienceTripGateway = $container->get(ExperienceTripGateway::class);
    $tripPlanner = $experienceTripGateway->getTripPlannerModule();
    
    $experiences = array_unique($experiences);
    foreach ($experiences as $deepLearningExperienceID) {
        $experienceGateway->syncExperienceMessengerGroup($deepLearningExperienceID);

        if (!empty($tripPlanner)) {
            $experienceTripGateway->syncTripStaff($deepLearningExperienceID);
            $experienceTripGateway->syncTripGroups($deepLearningExperienceID);
        }
    }
    

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";
    header("Location: {$URL}");
}
