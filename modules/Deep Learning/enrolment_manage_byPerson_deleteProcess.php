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
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceTripGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$params = [
    'origin'                  => $_POST['origin'] ?? '',
    'deepLearningEventID'     => $_POST['deepLearningEventID'] ?? '',
    'deepLearningEnrolmentID' => $_POST['deepLearningEnrolmentID'] ?? '',
];

$deepLearningEnrolmentID = $_POST['deepLearningEnrolmentID'] ?? '';
$origin = $_POST['origin'] ?? '';

$URL = $params['origin'] == 'byEvent'
    ? $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/enrolment_manage_byEvent.php&'.http_build_query($params)
    : $session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/enrolment_manage_byPerson.php&'.http_build_query($params);

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_byPerson_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($params['deepLearningEnrolmentID'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    // Validate the required values are present
    $enrolment = $container->get(EnrolmentGateway::class)->getByID($params['deepLearningEnrolmentID']);
    if (empty($enrolment)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    $experience = $experienceGateway->getExperienceDetailsByID($enrolment['deepLearningExperienceID']);
    if (empty($experience)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Validate the user exist
    $student = $studentGateway->selectActiveStudentByPerson($experience['gibbonSchoolYearID'], $enrolment['gibbonPersonID'])->fetch();
    if (empty($student)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $enrolmentGateway->delete($params['deepLearningEnrolmentID']);

    // Raise a new notification event
    if ($deleted) {
        $changeList = [
            __m('{student} ({formGroup}) - <i>{change} {experience} ({status})</i>', [
                'student'    => Format::name('', $student['preferredName'], $student['surname'], 'Student', false, true),
                'formGroup'  => $student['formGroup'],
                'change'     => __m('Removed from'),
                'experience' => $experience['name'],
                'status'     => $enrolment['status'],
            ]),
        ];

        $event = new NotificationEvent('Deep Learning', 'Enrolment Changes');
        $event->setNotificationText(__('{person} has made the following changes to {event} enrolment:', [
            'person' => Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true),
            'event' => $experience['eventName'] ?? __('Deep Learning'),
        ]).'<br/>'.Format::list($changeList));


        $staff = $container->get(StaffGateway::class)->selectTripLeadersByExperience($enrolment['deepLearningExperienceID']);
        foreach ($staff as $person) {
            $event->addRecipient($person['gibbonPersonID']);
        }

        $event->setActionLink("/index.php?q=/modules/Deep Learning/report_overview.php&deepLearningEventID=".$params['deepLearningEventID']);
        $event->sendNotifications($pdo, $session);
    }

    // Sync Trip Planner Students
    $experienceTripGateway = $container->get(ExperienceTripGateway::class);
    $tripPlanner = $experienceTripGateway->getTripPlannerModule();
    if (!empty($tripPlanner) && !empty($enrolment['deepLearningExperienceID'])) {
        $experienceTripGateway->syncTripStudents($enrolment['deepLearningExperienceID']);
        $experienceTripGateway->syncTripGroups($enrolment['deepLearningExperienceID']);
    }

    // Sync the messenger group (leaving)
    $experienceGateway->syncExperienceMessengerGroup($enrolment['deepLearningExperienceID']);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}
