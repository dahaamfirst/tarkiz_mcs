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
use Gibbon\Domain\User\UserGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $params = [
        'search'                   => $_REQUEST['search'] ?? '',
        'mode'                     => $_REQUEST['mode'] ?? '',
        'origin'                   => $_REQUEST['origin'] ?? '',
        'deepLearningEventID'      => $_REQUEST['deepLearningEventID'] ?? '',
        'deepLearningExperienceID' => $_REQUEST['deepLearningExperienceID'] ?? '',
        'deepLearningEnrolmentID'  => $_REQUEST['deepLearningEnrolmentID'] ?? '',
    ];

    if ($params['mode'] == 'add' && isset($_GET['editID'])) {
        $page->return->setEditLink($session->get('absoluteURL').'/index.php?q=/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php&mode=edit&deepLearningEnrolmentID='.$_GET['editID']);
    }

    if ($params['origin'] == 'byEvent') {
        $page->breadcrumbs->add(__m('Manage Enrolment by Event'), 'enrolment_manage_byEvent.php');

        $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Deep Learning', 'enrolment_manage_byEvent.php')->withQueryParams($params));
    } else {
        $page->breadcrumbs->add(__m('Manage Enrolment by Person'), 'enrolment_manage_byPerson.php');

        if (!empty($params['search'])) {
            $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Deep Learning', 'enrolment_manage_byPerson.php')->withQueryParams($params));
        }
    }

    $page->breadcrumbs->add($params['mode'] == 'add' ? __m('Add Enrolment') : __m('Edit Enrolment'));

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    // Get existing enrolment, if any
    $values = $params['mode'] == 'edit'
        ? $enrolmentGateway->getByID($params['deepLearningEnrolmentID'])
        : [];

    // Get events and experiences
    $events = $eventGateway->selectAllEvents($session->get('gibbonSchoolYearID'));

    if ($params['mode'] == 'edit') {
        $experience = $experienceGateway->getExperienceDetailsByID($values['deepLearningExperienceID']);
        $experienceList = $experienceGateway->selectExperiencesByEvent($experience['deepLearningEventID'])->fetchKeyPair();
    } else {
        $experiences = $experienceGateway->selectExperiences()->fetchAll();
        $experienceList = array_combine(array_column($experiences, 'deepLearningExperienceID'), array_column($experiences, 'name'));
        $experienceChainedTo = array_combine(array_column($experiences, 'deepLearningExperienceID'), array_column($experiences, 'deepLearningEventID'));
    }

    // FORM
    $form = Form::create('enrolment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/enrolment_manage_byPerson_addEditProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('mode', $params['mode']);
    $form->addHiddenValue('origin', $params['origin']);
    
    if ($params['mode'] == 'edit') {
        $form->addHiddenValue('deepLearningEnrolmentID', $params['deepLearningEnrolmentID']);
        $form->addHiddenValue('deepLearningEventID', $experience['deepLearningEventID']);

        $row = $form->addRow();
        $row->addLabel('event', __m('Event'));
        $row->addTextField('event')->readOnly()->setValue($experience['eventName']);

    } else {
        $row = $form->addRow();
        $row->addLabel('deepLearningEventID', __m('Event'));
        $row->addSelect('deepLearningEventID')
            ->fromResults($events, 'groupBy')
            ->required()
            ->placeholder()
            ->selected($experience['deepLearningEventID'] ?? $params['deepLearningEventID'] ?? '')
            ->readOnly($params['mode'] == 'edit');
    }

    $row = $form->addRow();
        $row->addLabel('deepLearningExperienceID', __('Enrolment'));
        $select = $row->addSelect('deepLearningExperienceID')
            ->fromArray($experienceList)
            ->required()
            ->placeholder()
            ->selected($params['deepLearningExperienceID'] ?? '');

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Person'));
    $row->addSelectUsers('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['includeStudents' => true])
        ->required()
        ->placeholder()
        ->readOnly($params['mode'] == 'edit');
                
    if ($params['mode'] == 'add') {
        $select->chainedTo('deepLearningEventID', $experienceChainedTo);
    }

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray(['Confirmed' => __m('Confirmed'), 'Pending' => __m('Pending')])
            ->required()
            ->selected('Confirmed');

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')->setRows(2);

    $person = $container->get(UserGateway::class)->getByID($values['gibbonPersonIDModified'] ?? '', ['preferredName', 'surname']);
    if (!empty($values['gibbonPersonIDModified']) && !empty($person)) {
        $row = $form->addRow();
        $row->addContent(Format::small(__m('Last modified by {name} on {date}', [
            'name' => Format::name('', $person['preferredName'], $person['surname'], 'Staff', false, true),
            'date' => Format::dateTimeReadable($values['timestampModified'] ?? ''),
        ])));
    }
    
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}
