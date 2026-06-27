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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_overview_editStatus.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $params = [
        'search'                   => $_REQUEST['search'] ?? '',
        'deepLearningEventID'      => $_REQUEST['deepLearningEventID'] ?? '',
        'deepLearningExperienceID' => $_REQUEST['deepLearningExperienceID'] ?? '',
        'deepLearningEnrolmentID'  => $_REQUEST['deepLearningEnrolmentID'] ?? '',
    ];

    $page->breadcrumbs
        ->add(__m('Deep Learning Overview'), 'report_overview.php', $params)
        ->add(__m('Edit Status'));

    $page->navigator->addSearchResultsAction(Url::fromModuleRoute('Deep Learning', 'report_overview.php')->withQueryParams($params));

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $staffGateway = $container->get(StaffGateway::class);

    // Get existing enrolment, if any
    $values = $enrolmentGateway->getByID($params['deepLearningEnrolmentID']);

    // Get events and experiences
    $events = $eventGateway->selectAllEvents($session->get('gibbonSchoolYearID'));
    $experience = $experienceGateway->getExperienceDetailsByID($values['deepLearningExperienceID']);
    $experienceList = $experienceGateway->selectExperiencesByEvent($experience['deepLearningEventID'])->fetchKeyPair();

    $staff = $staffGateway->getStaffExperienceAccess($values['deepLearningExperienceID'], $session->get('gibbonPersonID'));
    if ($highestAction != 'Deep Learning Overview_editAnyStatus' && (empty($staff) || $staff['role'] != 'Trip Leader')) {
        $page->addError(__m('You do not have edit access to this record.'));
        return;
    }

    // FORM
    $form = Form::create('enrolment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/report_overview_editStatusProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('search', $params['search']);
    $form->addHiddenValue('deepLearningEnrolmentID', $values['deepLearningEnrolmentID']);
    $form->addHiddenValue('deepLearningExperienceID', $values['deepLearningExperienceID']);
    $form->addHiddenValue('deepLearningEventID', $experience['deepLearningEventID']);

    $row = $form->addRow();
    $row->addLabel('event', __m('Event'));
    $row->addTextField('event')->readOnly()->setValue($experience['eventName']);

    $row = $form->addRow();
        $row->addLabel('experience', __('Enrolment'));
        $row->addTextField('event')->readOnly()->setValue($experience['name']);

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Person'));
    $row->addSelectUsers('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['includeStudents' => true])
        ->required()
        ->placeholder()
        ->readOnly(true);

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray(['Confirmed' => __m('Confirmed'), 'Pending' => __m('Pending'), ])
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
