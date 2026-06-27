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

use Gibbon\Services\Format;
use Gibbon\Data\Validator;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Domain\Messenger\GroupGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'gibbonSchoolYearID' => $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'),
    'search'             => $_REQUEST['search'] ?? ''
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/experience_manage_addMultiple.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage_add.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $experienceGateway = $container->get(ExperienceGateway::class);
    $staffGateway = $container->get(StaffGateway::class);
    $groupGateway = $container->get(GroupGateway::class);
    
    $data = [
        'deepLearningEventID'    => $_POST['deepLearningEventID'] ?? '',
        'timestampModified'      => date('Y-m-d H:i:s'),
        'gibbonPersonIDModified' => $session->get('gibbonPersonID'),
    ];

    $experiences = $_POST['experiences'] ?? [];

    // Validate the required values are present
    if (empty($data['deepLearningEventID']) || empty($experiences)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that the relational data exists
    $event = $container->get(EventGateway::class)->getByID($data['deepLearningEventID']);
    if (empty($event)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    foreach ($experiences as $experience) {
        // Check that the unit exists
        $unit = $container->get(UnitGateway::class)->getByID($experience['deepLearningUnitID']);
        if (empty($unit)) {
            $partialFail = true;
            continue;
        }

        // Set the default values for the experience
        $data['deepLearningUnitID'] = $experience['deepLearningUnitID'];
        $data['name'] = $unit['name'];
        $data['gibbonYearGroupIDList'] = $event['gibbonYearGroupIDList'];
        
        // Validate that this record is unique
        if (!$experienceGateway->unique($data, ['name', 'deepLearningEventID'])) {
            $partialFail = true;
            continue;
        }

        // Create the record
        $deepLearningExperienceID = $experienceGateway->insert($data);
        if (empty($deepLearningExperienceID)) {
            $partialFail = true;
            continue;
        }

        // Create the trip leader
        $deepLearningStaffID = $staffGateway->insert([
            'deepLearningExperienceID' => $deepLearningExperienceID,
            'gibbonPersonID'           => $experience['gibbonPersonID'],
            'role'                     => 'Trip Leader',
            'canEdit'                  => 'Y',
        ]);

        $partialFail = !$deepLearningStaffID;

        // Create the group
        $gibbonGroupID = $groupGateway->insertGroup([
            'gibbonPersonIDOwner' => $experience['gibbonPersonID'],
            'gibbonSchoolYearID'  => $params['gibbonSchoolYearID'],
            'name'                => $event['nameShort'].' '.$data['name'],
        ]);

        // Attach the group to the experience
        $experienceGateway->update($deepLearningExperienceID, [
            'gibbonGroupID' => $gibbonGroupID,
        ]);

        // Sync the Messenger Group participants
        $experienceGateway->syncExperienceMessengerGroup($deepLearningExperienceID);
    }
    
    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
