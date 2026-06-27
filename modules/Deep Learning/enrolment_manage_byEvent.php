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
use Gibbon\Tables\DataTable;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Http\Url;
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/enrolment_manage_byEvent.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Manage Enrolment by Event'));

    // CRITERIA
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $eventGateway = $container->get(EventGateway::class);

    $events = $eventGateway->selectEventsBySchoolYear($session->get('gibbonSchoolYearID'))->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($session->get('gibbonSchoolYearID'));

    $params = [
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? $activeEvent ?? '',
    ];

    // SEARCH
    $form = Form::create('filters', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/Deep Learning/enrolment_manage_byEvent.php');

    $row = $form->addRow();
        $row->addLabel('deepLearningEventID', __('Event'));
        $row->addSelect('deepLearningEventID')->fromArray($events)->placeholder()->selected($params['deepLearningEventID']);

    $row = $form->addRow();
        $row->addSearchSubmit($session, 'Clear Filters');

    echo $form->getOutput();

    // Nothing to display
    if (empty($params['deepLearningEventID'])) {
        return;
    }

    $experiences = $experienceGateway->selectExperiencesByEvent($params['deepLearningEventID'])->fetchKeyPair();

    if (empty($experiences)) {
        $experiences = [-1 => ''];
    }

    // TABLES
    foreach ($experiences as $deepLearningExperienceID => $experienceName) {

        // QUERY
        $criteria = $enrolmentGateway->newQueryCriteria()
            ->sortBy(['roleOrder', 'role', 'status', 'surname', 'preferredName'])
            ->fromPOST('experiences'.$deepLearningExperienceID);

        $enrolment = $enrolmentGateway->queryEnrolmentByExperience($criteria, $deepLearningExperienceID);

        $table = DataTable::createPaginated('experiences'.$deepLearningExperienceID, $criteria);
        $table->setTitle($experienceName);

        $table->modifyRows(function($values, $row) {
            if ($values['status'] == 'Pending') $row->addClass('warning');
            return $row;
        });

        $table->addMetaData('hidePagination', true);

        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php')
            ->addParam('deepLearningExperienceID', $deepLearningExperienceID)
            ->addParam('deepLearningEventID', $params['deepLearningEventID'])
            ->addParam('origin', 'byEvent')
            ->addParam('mode', 'add')
            ->displayLabel();


        $table->addColumn('student', __('Person'))
            ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
            ->width('25%')
            ->format(function ($values) {
                $output = Format::nameLinked($values['gibbonPersonID'], '', $values['preferredName'], $values['surname'], $values['roleCategory'], true, true);
                return $values['roleCategory'] == 'Student'
                    ? $output.'<br/>'.Format::small($values['formGroup'])
                    : $output;
            });

        $table->addColumn('role', __('Role'))
            ->width('12%')
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

        $table->addColumn('status', __('Status'))
            ->context('secondary')
            ->width('15%');

        $table->addColumn('notes', __('Notes'))
            ->format(Format::using('truncate', 'notes'))
            ->width('20%');

        // ACTIONS
        $table->addActionColumn()
            ->addParam('deepLearningEventID', $params['deepLearningEventID'])
            ->addParam('deepLearningExperienceID', $deepLearningExperienceID)
            ->addParam('deepLearningEnrolmentID')
            ->addParam('origin', 'byEvent')
            ->format(function ($values, $actions) {
                if (!empty($values['deepLearningEnrolmentID'])) {
                    $actions->addAction('edit', __('Edit'))
                            ->setURL('/modules/Deep Learning/enrolment_manage_byPerson_addEdit.php')
                            ->addParam('mode', 'edit');

                    $actions->addAction('delete', __('Delete'))
                            ->setURL('/modules/Deep Learning/enrolment_manage_byPerson_delete.php');
                }
                
            });

        echo $table->render($enrolment ?? []);
    }
}
