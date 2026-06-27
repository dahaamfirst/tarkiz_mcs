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
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\ChoiceGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\EnrolmentGenerator;
use Gibbon\Forms\MultiPartForm;



if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_groups.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('Manage DL Groups'));
     
    $page->return->addReturns([
        'error4' => __m(''),
    ]);

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($session->get('gibbonSchoolYearID'));
    
    $params = [
        'sidebar' => 'false',
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? $activeEvent ?? '',
    ];

    // FILTER
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/enrolment_manage_groups.php');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
    $row->addLabel('deepLearningEventID', __('Event'));
    $row->addSelect('deepLearningEventID')->fromArray($events)->selected($params['deepLearningEventID']);

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    if (empty($params['deepLearningEventID'])) return;

    // Get groups
    $signUpChoices = $container->get(SettingGateway::class)->getSettingByScope('Deep Learning', 'signUpChoices');
    $choiceList = [1 => __m('First Choice'), 2 => __m('Second Choice'), 3 => __m('Third Choice'), 4 => __m('Fourth Choice'), 5 => __m('Fifth Choice')];

    $experiences = $experienceGateway->selectExperienceDetailsByEvent($params['deepLearningEventID'])->fetchGroupedUnique();
    $enrolments = $enrolmentGateway->selectEnrolmentsByEvent($params['deepLearningEventID'])->fetchGroupedUnique();

    $criteria = $enrolmentGateway->newQueryCriteria();
    $unenrolled = $enrolmentGateway->queryUnenrolledStudentsByEvent($criteria, $params['deepLearningEventID'])->toArray();

    $enrolments = array_merge($enrolments, $unenrolled);
    
    $groups = [];

    foreach ($enrolments as $person) {
        for ($i = 1; $i <= $signUpChoices; $i++) {
            $person["choice{$i}"] = str_pad($person["choice{$i}"], 12, '0', STR_PAD_LEFT);
            $person["choice{$i}Name"] = $experiences[$person["choice{$i}"]]['name'] ?? '';
        }

        $groups[$person['deepLearningExperienceID']][$person['gibbonPersonID']] = $person;
    }

    // FORM
    $form = MultiPartForm::create('groups', $session->get('absoluteURL').'/modules/Deep Learning/enrolment_manage_groupsProcess.php');
    $form->setTitle(__m('Manage DL Groups'));
    $form->setClass('blank w-full');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('deepLearningEventID', $params['deepLearningEventID']);

    $form->addHeaderAction('generate', __m('Generate DL Groups'))
        ->setURL('/modules/Deep Learning/choices_manage_generate.php')
        ->addParam('deepLearningEventID', $params['deepLearningEventID'])
        ->addParam('sidebar', 'false')
        ->setIcon('run')
        ->displayLabel();

    // Display the drag-drop group editor
    $form->addRow()->addContent($page->fetchFromTemplate('generate.twig.html', [
        'signUpChoices' => $signUpChoices,
        'experiences' => $experiences,
        'groups'      => $groups,
        'mode' => 'student',
    ]));

    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full');
    $row = $table->addRow()->addSubmit(__('Submit'));
    
    echo $form->getOutput();
}
