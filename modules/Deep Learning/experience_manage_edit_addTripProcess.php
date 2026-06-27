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

use Gibbon\Http\Url;
use Gibbon\Data\Validator;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceTripGateway;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\EventDateGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'deepLearningExperienceID' => $_REQUEST['deepLearningExperienceID'] ?? '',
    'gibbonSchoolYearID'       => $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'),
    'search'                   => $_REQUEST['search'] ?? ''
];

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/experience_manage_edit.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $unitGateway = $container->get(UnitGateway::class);
    $eventGateway = $container->get(EventGateway::class);
    $eventDateGateway = $container->get(EventDateGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $experienceTripGateway = $container->get(ExperienceTripGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    $highestAction = getHighestGroupedAction($guid, '/modules/Deep Learning/experience_manage_edit.php', $connection2);
    $canEditExperience = $experienceGateway->getExperienceEditAccess($params['deepLearningExperienceID'], $session->get('gibbonPersonID'));
    if ($highestAction != 'Manage Experiences_all' && $canEditExperience != 'Y') {
        $URL .= '&return=error0';
        header("Location: {$URL}");
        exit;
    }
    
    $type = $_POST['type'] ?? '';
    $tripName = $_POST['tripName'] ?? '';
    $createMessengerGroup = $_POST['createMessengerGroup'] ?? 'N';
    $syncParticipants = $_POST['syncParticipants'] ?? 'N';
    $tripPlannerRequestID = intval($_POST['tripPlannerRequestID'] ?? '');
    $deepLearningEventDateIDList = !empty($_POST['deepLearningEventDateIDList']) ? implode(',', $_POST['deepLearningEventDateIDList']) : null;

    // Validate the required values are present
    if (empty($params['deepLearningExperienceID']) || empty($type) ) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $experience = $experienceGateway->getExperienceDetailsByID($params['deepLearningExperienceID']);
    if (empty($experience)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    if ($type == 'new') {
        if (empty($tripName)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Get the unit details
        $unit = $unitGateway->getByID($experience['deepLearningUnitID']);
        if (empty($unit)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Create the trip request
        $data = [
            'title'                    => $tripName,
            'description'              => $unit['description'] ?? '',
            'location'                 => $unit['location'] ?? '',
            'letterToParents'          => $unit['letterToParents'] ?? '',
            'riskAssessment'           => $unit['riskAssessment'] ?? '',
            'status'                   => 'Draft',
            'gibbonSchoolYearID'       => $experience['gibbonSchoolYearID'],
            'creatorPersonID'          => $session->get('gibbonPersonID'),
            'messengerGroupID'         => null,
            'deepLearningExperienceID' => $params['deepLearningExperienceID'],
            'deepLearningSync'         => 'Y',
        ];

        $tripPlannerRequestID = $experienceTripGateway->insertTripRequest($data);
        if (empty($tripPlannerRequestID)) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Add staff and students
        $criteria = $enrolmentGateway->newQueryCriteria();
        $enrolment = $enrolmentGateway->queryEnrolmentByExperience($criteria, $params['deepLearningExperienceID'], true)->toArray();
        foreach ($enrolment as $person) {
            $inserted = $experienceTripGateway->insertTripPerson([
                'tripPlannerRequestID' => $tripPlannerRequestID,
                'gibbonPersonID'       => $person['gibbonPersonID'],
                'role'                 => $person['roleCategory'] == 'Staff' ? 'Teacher' : 'Student',
            ]);
            $partialFail &= !$inserted;
        }

        // Add days
        $dateIDs = explode(',', $deepLearningEventDateIDList);
        foreach ($dateIDs as $deepLearningEventDateID) {
            $eventDate = $eventDateGateway->getByID($deepLearningEventDateID);
            $inserted = $experienceTripGateway->insertTripDays([
                'tripPlannerRequestID' => $tripPlannerRequestID,
                'startDate'            => $eventDate['eventDate'],
                'endDate'              => $eventDate['eventDate'],
                'allDay'               => 1,
                'startTime'            => $eventDate['timeStart'] ?? '00:00:00',
                'endTime'              => $eventDate['timeEnd'] ?? '00:00:00',
            ]);
            $partialFail &= !$inserted;
        }
        
        // Create messenger group
        if ($createMessengerGroup == 'Y') {
            $gibbonGroupID = $experienceTripGateway->insertGroup([
                'gibbonSchoolYearID'  => $experience['gibbonSchoolYearID'],
                'gibbonPersonIDOwner' => $session->get('gibbonPersonID'),
                'name'                => $tripName,
                'timestampCreated'    => date('Y-m-d H:i:s'),
                'timestampUpdated'    => date('Y-m-d H:i:s'),
            ]);
            $partialFail &= !$inserted;

            if (!empty($gibbonGroupID)) {
                // Add group participants
                foreach ($enrolment as $person) {
                    $inserted = $experienceTripGateway->insertGroupPerson([
                        'gibbonGroupID'  => $gibbonGroupID,
                        'gibbonPersonID' => $person['gibbonPersonID'],
                    ]);
                    $partialFail &= !$inserted;
                }

                // Update to attach the group to the trip planner
                $experienceTripGateway->updateTripRequest($tripPlannerRequestID, ['messengerGroupID' => $gibbonGroupID]);
                $experienceGateway->update($params['deepLearningExperienceID'], ['gibbonGroupID' => $gibbonGroupID]);
            }
        } else {
            $experienceTripGateway->updateTripRequest($tripPlannerRequestID, ['messengerGroupID' => $experience['gibbonGroupID']]);
        }

        // Update the trip request and experience to link to each other
        $experienceTripGateway->attachTripRequest($params['deepLearningExperienceID'], $tripPlannerRequestID, $deepLearningEventDateIDList);

        // Redirect to trip request edit page
        $URL = Url::fromModuleRoute('Trip Planner', 'trips_submitRequest')->withQueryParams([
            'tripPlannerRequestID'     => $tripPlannerRequestID,
            'deepLearningExperienceID' => $params['deepLearningExperienceID'],
            'mode'                     => 'edit',
        ]);
        header("Location: {$URL}&return=success2");
        exit;

    } else if ($type == 'existing') {
        if (empty($tripPlannerRequestID)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Update the trip request and experience to link to each other
        $tripRequest = $experienceTripGateway->attachTripRequest($params['deepLearningExperienceID'], $tripPlannerRequestID, $deepLearningEventDateIDList);

        // Update the experience group if the trip already has one and the experience doesn't
        if (empty($experience['gibbonGroupID']) && !empty($tripRequest['messengerGroupID'])) {
            $experience['gibbonGroupID'] = $tripRequest['messengerGroupID'];
            $experienceGateway->update($params['deepLearningExperienceID'], ['gibbonGroupID' => $experience['gibbonGroupID']]);
        }

        // Sync trip participants
        if ($syncParticipants == 'Y') {
            $experienceTripGateway->updateTripRequest($tripPlannerRequestID, [
                'deepLearningSync' => 'Y', 
                'messengerGroupID' => $experience['gibbonGroupID'],
            ]);
            $experienceTripGateway->syncTripStaff($params['deepLearningExperienceID']);
            $experienceTripGateway->syncTripStudents($params['deepLearningExperienceID']);
            $experienceTripGateway->syncTripGroups($params['deepLearningExperienceID']);
            $experienceGateway->syncExperienceMessengerGroup($params['deepLearningExperienceID']);
        }
    }

    // Do stuff
    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}
