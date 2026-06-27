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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\MultiPartForm;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_staffing.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('Manage DL Staffing'));

    
    $page->return->addReturns([
        'error4' => __m(''),
    ]);

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $staffGateway = $container->get(StaffGateway::class);

    $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($session->get('gibbonSchoolYearID'));

    $params = [
        'sidebar' => 'false',
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? $activeEvent ?? '',
    ];

    // FILTER
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/enrolment_manage_staffing.php');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
    $row->addLabel('deepLearningEventID', __('Event'));
    $row->addSelect('deepLearningEventID')->fromArray($events)->selected($params['deepLearningEventID']);

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (empty($params['deepLearningEventID'])) return;

    // Get staffing

    $experiences = $experienceGateway->selectExperienceDetailsByEvent($params['deepLearningEventID'])->fetchGroupedUnique();
    $staffing = $staffGateway->selectStaffByEvent($params['deepLearningEventID'])->fetchGroupedUnique();

    $criteria = $staffGateway->newQueryCriteria();
    $unassigned = $staffGateway->queryUnassignedStaffByEvent($criteria, $params['deepLearningEventID'])->toArray();

    $staffing = array_merge($staffing, $unassigned);

    $groups = [];

    foreach ($staffing as $person) {
        // for ($i = 1; $i <= $signUpChoices; $i++) {
        //     $person["choice{$i}"] = str_pad($person["choice{$i}"], 12, '0', STR_PAD_LEFT);
        //     $person["choice{$i}Name"] = $experiences[$person["choice{$i}"]]['name'] ?? '';
        // }

        if (!empty($person['type']) && $person['type'] == 'Teaching') {
            $person['type'] = 'Teacher';
        }

        $groups[$person['deepLearningExperienceID']][$person['gibbonPersonID']] = $person;
    }

    // FORM
    $form = MultiPartForm::create('groups', $session->get('absoluteURL').'/modules/Deep Learning/enrolment_manage_staffingProcess.php');
    $form->setTitle(__m('Manage DL Staffing'));
    $form->setClass('blank w-full');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('deepLearningEventID', $params['deepLearningEventID']);

    // Display the drag-drop group editor
    $form->addRow()->addContent($page->fetchFromTemplate('generate.twig.html', [
        'experiences' => $experiences,
        'groups'      => $groups,
        'mode'        => 'staff',
    ]));

    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
    $row = $table->addRow()->addSubmit(__('Submit'));
    
    echo $form->getOutput();
}
