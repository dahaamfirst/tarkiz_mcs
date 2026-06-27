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
use Gibbon\Tables\Prefab\ReportTable;
use Gibbon\Tables\Renderer\SpreadsheetRenderer;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_overview.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $page->breadcrumbs->add(__m('Deep Learning Overview'));

    // Setup data
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $viewMode = $_REQUEST['format'] ?? '';

    // Setup gateways
    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($session->get('gibbonSchoolYearID'));
    
    $params = [
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? $activeEvent ?? '',
        'deepLearningExperienceID' => $_REQUEST['deepLearningExperienceID'] ?? '',
    ];

    if (empty($events)) {
        $page->addMessage(__m('There are no active Deep Learning events.'));
        return;
    }
    
    if (empty($viewMode)) {
        // FILTER
        $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');

        $form->setTitle(__('Filter'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_overview.php');
        $form->addHiddenValue('address', $session->get('address'));

        $row = $form->addRow();
        $row->addLabel('deepLearningEventID', __('Event'));
        $row->addSelect('deepLearningEventID')->fromArray($events)->placeholder()->selected($params['deepLearningEventID']);

        $experienceList = $experienceGateway->selectExperiencesByEvent($params['deepLearningEventID'])->fetchKeyPair();
        $row = $form->addRow();
        $row->addLabel('deepLearningExperienceID', __('Experience'));
        $row->addSelect('deepLearningExperienceID')
            ->fromArray($experienceList)
            ->placeholder()
            ->selected($params['deepLearningExperienceID'] ?? '');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    // Nothing to display
    if (empty($params['deepLearningEventID'])) {
        return;
    }

    // Get staff access details
    $staffByExperience = $container->get(StaffGateway::class)->selectStaffByEventAndPerson($params['deepLearningEventID'], $session->get('gibbonPersonID'))->fetchGroupedUnique();

    $experiences = $experienceGateway->selectExperienceDetailsByEvent($params['deepLearningEventID'])->fetchGroupedUnique();

    if (!empty($params['deepLearningExperienceID'])) {
        $experiences = [$params['deepLearningExperienceID'] => $experiences[$params['deepLearningExperienceID']] ?? ''];
    }

    if (empty($experiences)) {
        $experiences = [-1 => ''];
    } else if (empty($viewMode)) {
        $form = Form::create('print', '');
        $form->addHeaderAction('print', __m('Print All'))
            ->setUrl('/report.php')
            ->addParam('q', '/modules/Deep Learning/report_overview.php')
            ->addParam('deepLearningEventID', $params['deepLearningEventID'])
            ->addParam('format', 'print')
            ->setTarget('_blank')
            ->directLink()
            ->displayLabel();

        $form->addHeaderAction('export', __('Export All'))
            ->setURL('/export.php')
            ->addParam('q', '/modules/Deep Learning/report_overview.php')
            ->addParam('deepLearningEventID', $params['deepLearningEventID'])
            ->addParam('format', 'export')
            ->setTarget('_blank')
            ->directLink()
            ->displayLabel();

        echo $form->getOutput();
    }

    $renderer = $container->get(SpreadsheetRenderer::class);
    // TABLES
    foreach ($experiences as $deepLearningExperienceID => $experience) {

        $_GET['deepLearningExperienceID'] = $deepLearningExperienceID;
        $staff = $staffByExperience[$deepLearningExperienceID] ?? [];

        // QUERY
        $criteria = $enrolmentGateway->newQueryCriteria()
            ->sortBy(['roleOrder', 'role', 'status', 'surname', 'preferredName'])
            ->fromPOST('report_overview'.$deepLearningExperienceID);

        $enrolment = $enrolmentGateway->queryEnrolmentByExperience($criteria, $deepLearningExperienceID);

        $table = ReportTable::createPaginated('report_overview'.$deepLearningExperienceID, $criteria)->setViewMode($viewMode, $session);
        $table->setTitle($experience['name']);
        $table->setDescription(__m('Location').': '.$experience['location'].(!empty($experience['provider']) ? ' ('.__m('Provider').': '.$experience['provider'].')' : ''));

        if ($viewMode == 'export' && count($experiences) > 1) {
            $table->setRenderer($renderer);
            $table->addColumn('name', __('Experience Name'));
        } else {
            $table->addColumn('image_240', __('Photo'))
                    ->context('primary')
                    ->width('8%')
                    ->notSortable()
                    ->format(Format::using('userPhoto', ['image_240', 'xs']));
        }

        $table->modifyRows(function($values, $row) {
            if ($values['role'] == 'Trip Leader') $row->addClass('success');
            elseif ($values['role'] == 'Teacher') $row->addClass('message');
            elseif ($values['role'] == 'Support') $row->addClass('bg-purple-200');
            if ($values['status'] == 'Pending') $row->addClass('warning');
            return $row;
        });

        $table->addMetaData('hidePagination', true);
        $table->addMetaData('hideHeaderActions', true);
        $table->addMetaData('tableCount', count($experiences));
        
        $table->addColumn('student', __('Person'))
            ->description(__('Status'))
            ->sortable(['surname', 'preferredName'])
            ->width('25%')
            ->format(function ($values) {
                return Format::nameLinked($values['gibbonPersonID'], '', $values['preferredName'], $values['surname'], $values['roleCategory'], true, true);
            })
            ->formatDetails(function ($values) {
                return $values['roleCategory'] == 'Student'
                    ? Format::small($values['status'])
                    : Format::small($values['role']);
            });

        $table->addColumn('formGroup', __('Form Group'))
            ->width('6%')
            ->context('secondary');

        $choices = ['1' => __m('1st'), '2' => __m('2nd'), '3' => __m('3rd'), '4' => __m('4th'), '5' => __m('5th')];
        $table->addColumn('choice', __m('Choice'))
            ->width('5%')
            ->format(function ($values) use ($choices) {
                switch ($values['choice']) {
                    case 1: $class = 'success'; break;
                    case 2: $class = 'message'; break;
                    case 3: $class = 'warning'; break;
                    case 4: $class = 'warning'; break;
                    case 5: $class = 'warning'; break;
                    default: $class = 'error'; break;
                }
                return Format::tag($choices[$values['choice']] ?? $values['choice'], $class);
            });

        $table->addColumn('notes', __('Notes'))
            ->format(Format::using('truncate', 'notes'));

        // ACTIONS
        if ($highestAction == 'Deep Learning Overview_editAnyStatus' || (!empty($staff) && ($staff['role'] == 'Trip Leader' || $staff['canEdit'] == 'Y'))) {
            
            $table->addActionColumn()
                ->addParam('search', $criteria->getSearchText(true))
                ->addParam('deepLearningEventID', $params['deepLearningEventID'])
                ->addParam('deepLearningExperienceID', $deepLearningExperienceID)
                ->addParam('deepLearningEnrolmentID')
                ->format(function ($values, $actions) {
                    if ($values['roleCategory'] == 'Student') {
                        $actions->addAction('edit', __('Edit'))
                                ->setURL('/modules/Deep Learning/report_overview_editStatus.php')
                                ->addParam('mode', 'edit');
                    }
                });
        }

        if ($viewMode == 'print') {
            $table->setHeader([]);
        }

        echo $table->render($enrolment ?? []);
    }

    if ($viewMode == 'export' && count($experiences) > 1) {
        $table->getRenderer()->save($table);
    }
}
