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
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Deep Learning Events'));

    $canViewInactive = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_viewInactive');

    // Query events
    $eventGateway = $container->get(EventGateway::class);

    $criteria = $eventGateway->newQueryCriteria()
        ->filterBy('active', 'Y')
        ->sortBy(['startDate'])
        ->fromPOST();

    $events = $eventGateway->queryEvents($criteria, $session->get('gibbonSchoolYearID'));

    $page->writeFromTemplate('events.twig.html', [
        'welcomeText'     => $container->get(SettingGateway::class)->getSettingByScope('Deep Learning', 'welcomeText'),
        'events'          => $events->toArray(),
        'canViewInactive' => $canViewInactive,
    ]);
}
