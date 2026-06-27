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
use Gibbon\Services\Format;
use Gibbon\Comms\NotificationEvent;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceTripGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'mode'                    => $_POST['mode'] ?? '',
    'origin'                  => $_POST['origin'] ?? '',
    'deepLearningEventID'     => $_POST['deepLearningEventID'] ?? '',
    'deepLearningEnrolmentID' => $_POST['deepLearningEnrolmentID'] ?? '',
];

$URL = $params['origin'] == 'byEvent'
    ? $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php&'.http_build_query($params)
    : $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $choiceGateway = $container->get(ChoiceGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    $data = [
        'deepLearningExperienceID' => $_POST['deepLearningExperienceID'] ?? '',
        'deepLearningEventID'      => $_POST['deepLearningEventID'] ?? '',
        'gibbonPersonID'           => $_POST['gibbonPersonID'] ?? '',
        'status'                   => $_POST['status'] ?? '',
        'notes'                    => $_POST['notes'] ?? '',
        'timestampCreated'         => date('Y-m-d H:i:s'),
        'gibbonPersonIDCreated'    => $session->get('gibbonPersonID'),
    ];

    // Validate the required values are present
    if (empty($data['deepLearningExperienceID']) || empty($data['deepLearningEventID']) || empty($data['gibbonPersonID']) || empty($data['status'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $experience = $experienceGateway->getExperienceDetailsByID($data['deepLearningExperienceID']);
    if (empty($experience)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate the user exist
    $student = $studentGateway->selectActiveStudentByPerson($experience['gibbonSchoolYearID'], $data['gibbonPersonID'])->fetch();
    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Check if this enrolment can be attached to a choice
    $choice = $choiceGateway->selectBy([
        'deepLearningExperienceID' => $data['deepLearningExperienceID'],
        'gibbonPersonID' => $data['gibbonPersonID']],
    )->fetch();

    $data['deepLearningChoiceID'] = !empty($choice)
        ? $choice['deepLearningChoiceID']
        : null;
    
    // Check existing enrolment
    $enrolment = $enrolmentGateway->getEventEnrolmentByPerson($data['deepLearningEventID'], $data['gibbonPersonID']);

    // Create the record
    $deepLearningEnrolmentID = $enrolmentGateway->insertAndUpdate($data, [
        'deepLearningExperienceID' => $_POST['deepLearningExperienceID'] ?? '',
        'status'                   => $_POST['status'] ?? '',
        'notes'                    => $_POST['notes'] ?? '',
        'timestampModified'        => date('Y-m-d H:i:s'),
        'gibbonPersonIDModified'   => $session->get('gibbonPersonID'),
    ]);

    if (!empty($enrolment) && $enrolment['deepLearningExperienceID'] != $data['deepLearningExperienceID']) {
        $change = __m('Moved to');
    } elseif (!empty($enrolment) && $enrolment['status'] != $data['status']) {
        $change = __m('Status changed for');
    } elseif (empty($enrolment)) {
        $change = __m('Added to');
    }

    // Collect a list of enrolment changes
    if (!empty($change)) {
        $changeList = [
            __m('{student} ({formGroup}) - <i>{change} {experience} ({status})</i>', [
                'student'    => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
                'formGroup'  => $student['formGroup'],
                'change'     => $change,
                'experience' => $experience['name'],
                'status'     => $data['status'],
            ]),
        ];

        // Raise a new notification event
        $event = new NotificationEvent('Deep Learning', 'Enrolment Changes');
        $event->setNotificationText(__('{person} has made the following changes to {event} enrolment:', [
            'person' => Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true),
            'event' => $experience['eventName'] ?? __('Deep Learning'),
        ]).'<br/>'.Format::list($changeList));

        // Add trip leaders
        $experiences = [];
        if (!empty($data['deepLearningExperienceID'])) {
            $experiences[] = $data['deepLearningExperienceID'];
        }
        if (!empty($enrolment['deepLearningExperienceID']) && $enrolment['deepLearningExperienceID'] != $data['deepLearningExperienceID']) {
            $experiences[] = $enrolment['deepLearningExperienceID'];
        }
        $staff = $container->get(StaffGateway::class)->selectTripLeadersByExperience($experiences);
        foreach ($staff as $person) {
            $event->addRecipient($person['gibbonPersonID']);
        }

        $event->setActionLink("/index.php?q=/modules/Deep Learning/report_overview.php&deepLearningEventID=".$params['deepLearningEventID']);
        $event->sendNotifications($pdo, $session);
    }

    // Sync Trip Planner Students
    $experienceTripGateway = $container->get(ExperienceTripGateway::class);
    $tripPlanner = $experienceTripGateway->getTripPlannerModule();
    if (!empty($tripPlanner)) {
        if (!empty($data['deepLearningExperienceID'])) {
            $experienceTripGateway->syncTripStudents($data['deepLearningExperienceID']);
            $experienceTripGateway->syncTripGroups($data['deepLearningExperienceID']);
        }
        if (!empty($enrolment['deepLearningExperienceID']) && $enrolment['deepLearningExperienceID'] != $data['deepLearningExperienceID']) {
            $experienceTripGateway->syncTripStudents($enrolment['deepLearningExperienceID']);
            $experienceTripGateway->syncTripGroups($enrolment['deepLearningExperienceID']);
        }
    }

    // Sync the messenger group (entering)
    if (!empty($data['deepLearningExperienceID'])) {
        $experienceGateway->syncExperienceMessengerGroup($data['deepLearningExperienceID']);
    }

    // Sync the messenger group (leaving)
    if (!empty($enrolment['deepLearningExperienceID']) && $enrolment['deepLearningExperienceID'] != $data['deepLearningExperienceID']) {
        $experienceGateway->syncExperienceMessengerGroup($enrolment['deepLearningExperienceID']);
    }
    
    
    $URL .= $params['mode'] == 'add' && empty($deepLearningEnrolmentID)
        ? "&return=warning1"
        : "&return=success0";

    header($params['mode'] == 'add'
        ? "Location: {$URL}&$editID={$deepLearningEnrolmentID}"
        : "Location: {$URL}"
    );
}
