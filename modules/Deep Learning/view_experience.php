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

use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\UnitPhotoGateway;
use Gibbon\Module\DeepLearning\Domain\UnitBlockGateway;
use Gibbon\Module\DeepLearning\Domain\UnitGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/view_experience.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $deepLearningEventID = $_REQUEST['deepLearningEventID'] ?? '';
    $deepLearningExperienceID = $_REQUEST['deepLearningExperienceID'] ?? '';

    $page->breadcrumbs
        ->add(__m('Deep Learning Events'), 'view.php')
        ->add(__m('Explore'), 'view_event.php', ['deepLearningEventID' => $deepLearningEventID, 'sidebar' => 'false'])
        ->add(__m('View Experience'));

    $page->return->addReturns([
        'error4' => __m('Sign up is currently not available for this Deep Learning event.'),
        'error5' => __m('There was an error verifying your Deep Learning choices. Please try again.'),
    ]);

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $canSignUp = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_signUp');
    $canViewInactive = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_viewInactive');

    // Check records exist and are available
    $unitGateway = $container->get(UnitGateway::class);
    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $unitPhotoGateway = $container->get(UnitPhotoGateway::class);
    $unitBlockGateway = $container->get(UnitBlockGateway::class);
    $staffGateway = $container->get(StaffGateway::class);

    if (empty($deepLearningExperienceID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $experience = $experienceGateway->getExperienceDetailsByID($deepLearningExperienceID);
    $event = $eventGateway->getEventDetailsByID($experience['deepLearningEventID'] ?? '');

    if (empty($experience) || empty($event)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if (($experience['active'] != 'Y' || $event['active'] != 'Y') && !$canViewInactive) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    if ($event['viewable'] != 'Y'  && !$canViewInactive) {
        $page->addMessage(__m('This event is not viewable at this time. Please return to the Events page to explore a different event.'));
        return;
    }

    // Get photos & blocks
    $experience['photos'] = $unitPhotoGateway->selectPhotosByExperience($deepLearningExperienceID)->fetchAll();
    $experience['blocks'] = $unitBlockGateway->selectBlocksByUnit($experience['deepLearningUnitID'])->fetchAll();

    // Check sign-up access
    $now = (new DateTime('now'))->format('U');
    $signUpIsOpen = false;
    $isPastEvent = false;

    if (!empty($event['accessOpenDate']) && !empty($event['accessCloseDate'])) {
        $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $event['accessOpenDate'])->format('U');
        $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $event['accessCloseDate'])->format('U');

        $signUpIsOpen = $accessOpenDate <= $now && $accessCloseDate >= $now;
    }

    if (!empty($event['endDate'])) {
        $endDate = DateTime::createFromFormat('Y-m-d', $event['endDate'])->format('U');
        $isPastEvent = $now >= $endDate;
    }

    $signUpEvent = $eventGateway->getEventSignUpAccess($experience['deepLearningEventID'], $session->get('gibbonPersonID'));
    $signUpExperience = $experienceGateway->getExperienceSignUpAccess($deepLearningExperienceID, $session->get('gibbonPersonID'));

    $enrolment = $enrolmentGateway->getExperienceDetailsByEnrolment($experience['deepLearningEventID'], $session->get('gibbonPersonID'), $deepLearningExperienceID);

    $canEditAll = getHighestGroupedAction($guid, '/modules/Deep Learning/unit_manage_edit.php', $connection2) == 'Manage Units_all';
    $canEditUnit = $unitGateway->getUnitEditAccess($experience['deepLearningUnitID'], $session->get('gibbonPersonID')) ?? 'N';
    $isStaff = $staffGateway->getStaffExperienceAccess($deepLearningExperienceID, $session->get('gibbonPersonID'));

    $page->writeFromTemplate('experience.twig.html', [
        'event'      => $event,
        'experience' => $experience,

        'nextExperience' => $experienceGateway->getNextExperienceByID($deepLearningEventID, $deepLearningExperienceID),
        'prevExperience' => $experienceGateway->getPreviousExperienceByID($deepLearningEventID, $deepLearningExperienceID),

        'canViewInactive' => $canViewInactive,
        'canSignUp'  => $canSignUp,
        'signUpIsOpen' => $signUpIsOpen,
        'signUpAccess' => $signUpEvent && $signUpExperience,

        'isPastEvent' => $isPastEvent,
        'isEnrolled' => !empty($enrolment) && $enrolment['deepLearningExperienceID'] == $deepLearningExperienceID,
        'enrolment' => $enrolment,

        'canEditUnit' => $canEditAll || (!empty($canEditUnit) && $canEditUnit == 'Y'),
        'isStaff' => !empty($isStaff),
    ]);
}
