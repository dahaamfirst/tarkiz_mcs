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

use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/view_event.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Deep Learning Events'), 'view.php')
        ->add(__m('Explore'));

    $deepLearningEventID = $_REQUEST['deepLearningEventID'] ?? '';

    $canViewInactive = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_viewInactive');

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);

    if (empty($deepLearningEventID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $event = $eventGateway->getEventDetailsByID($deepLearningEventID);

    if (empty($event)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    if ($event['active'] != 'Y' && !$canViewInactive) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    if ($event['viewable'] != 'Y' && !$canViewInactive) {
        $page->addMessage(__m('This event is not viewable at this time. Please return to the Events page to explore a different event.'));
        return;
    }

    // Query experiences
    $criteria = $experienceGateway->newQueryCriteria()
        ->filterBy('active', 'Y')
        ->sortBy(['name'])
        ->fromPOST();

    $experiences = $experienceGateway->queryExperiencesByEvent($criteria, $deepLearningEventID);

    $page->writeFromTemplate('experiences.twig.html', [
        'event'           => $event,
        'experiences'     => $experiences->toArray(),
        'canViewInactive' => $canViewInactive,
    ]);
}
