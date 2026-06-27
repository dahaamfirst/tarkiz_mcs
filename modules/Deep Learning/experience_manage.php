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
use Gibbon\Module\DeepLearning\Domain\ExperienceGateway;
use Gibbon\Http\Url;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Module\DeepLearning\Domain\ExperienceTripGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('Manage Experiences'));

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    $highestTripAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if (empty($highestAction)) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }

    $eventGateway = $container->get(EventGateway::class);
    $experienceGateway = $container->get(ExperienceGateway::class);
    $experienceTripGateway = $container->get(ExperienceTripGateway::class);
    
    $params = [
        'gibbonSchoolYearID' => $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'),
        'deepLearningEventID' => $_REQUEST['deepLearningEventID'] ?? null,
        'search'             => $_REQUEST['search'] ?? ''
    ];

    $events = $eventGateway->selectEventsBySchoolYear($params['gibbonSchoolYearID'])->fetchKeyPair();
    $activeEvent = $eventGateway->getNextActiveEvent($params['gibbonSchoolYearID']);

    $page->navigator->addSchoolYearNavigation($params['gibbonSchoolYearID']);

    // Setup criteria
    $criteria = $experienceGateway->newQueryCriteria(true)
        ->searchBy($experienceGateway->getSearchableColumns(), $params['search'])
        ->filterBy('event', $highestAction == 'Manage Experiences_all' ?($params['deepLearningEventID'] ?? $activeEvent) : null)
        ->sortBy(['eventName', 'name'])
        ->fromPOST();

    // Search
    if ($highestAction == 'Manage Experiences_all') {
        $form = Form::create('filters', $session->get('absoluteURL').'/index.php', 'get');
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/Deep Learning/experience_manage.php');
        $form->addHiddenValue('gibbonSchoolYearID', $params['gibbonSchoolYearID']);

        $row = $form->addRow();
            $row->addLabel('deepLearningEventID', __('Event'));
            $row->addSelect('deepLearningEventID')->fromArray($events)->placeholder()->selected($params['deepLearningEventID'] ?? $activeEvent);

        $row = $form->addRow();
            $row->addLabel('search', __('Search For'))->description(__m('Experience name, unit name, event name'));
            $row->addTextField('search')->setValue($criteria->getSearchText())->maxLength(20);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session, 'Clear Filters', ['view', 'sidebar', 'gibbonSchoolYearID']);

        echo $form->getOutput();
    }

    // Query experiences
    $gibbonPersonID = $highestAction == 'Manage Experiences_my' ? $session->get('gibbonPersonID') : null;
    $yearGroupCount = $container->get(YearGroupGateway::class)->getYearGroupCount();

    $experiences = $experienceGateway->queryExperiences($criteria, $params['gibbonSchoolYearID'], $gibbonPersonID);
    $tripRequests = !empty($highestTripAction)
        ? $experienceTripGateway->selectDeepLearningTripRequests($params['gibbonSchoolYearID'])->fetchKeyPair()
        : [];

    // Render table
    $table = DataTable::createPaginated('experiences', $criteria);

    if ($highestAction == 'Manage Experiences_all') {

        $table->addHeaderAction('addMultiple', __('Add Multiple'))
            ->setURL('/modules/Deep Learning/experience_manage_addMultiple.php')
            ->addParams($params)
            ->displayLabel();
    } else {
        $table->setDescription(__m('This section shows all Deep Learning experiences that you are a member of.'));
    }

    $table->modifyRows(function($values, $row) {
        if ($values['active'] == 'N') $row->addClass('error');
        return $row;
    });

    $table->addColumn('eventNameShort', __('Event'))
        ->width('8%');

    $table->addColumn('name', __('Name'))
        ->description(__('Year Groups'))
        ->context('primary')
        ->format(function ($values) {
            $url = Url::fromModuleRoute('Deep Learning', 'view_experience.php')->withQueryParams(['deepLearningExperienceID' => $values['deepLearningExperienceID'], 'sidebar' => 'false']);
            return $values['active'] == 'Y' && $values['viewable'] == 'Y' 
                ? Format::link($url, $values['name'])
                : $values['name'];
        })
        ->formatDetails(function ($values) use ($yearGroupCount) {
            return Format::small($values['yearGroupCount'] >= $yearGroupCount ? __m('All Year Groups') : $values['yearGroups']);
        });

    $table->addColumn('tripLeaders', __m('Trip Leader(s)'));

    if (!empty($highestTripAction)) {
        $table->addColumn('tripPlan', __('Trip Plan'))
            ->width('8%')
            ->format(function ($values) use ($tripRequests, $params) {
                $tripPlannerRequestID = $tripRequests[$values['deepLearningExperienceID']] ?? [];
                if (empty($tripPlannerRequestID)) return '';

                $url = Url::fromModuleRoute('Trip Planner', 'trips_requestView.php')->withQueryParams(['gibbonSchoolYearID' => $params['gibbonSchoolYearID'], 'tripPlannerRequestID' => $tripPlannerRequestID]);
                return Format::link($url, __('View'));
            });
    }

    $table->addColumn('gibbonGroupID', __('Messenger Group'))
        ->width('8%')
        ->format(function ($values) {
            if (empty($values['gibbonGroupID'])) return '';

            $url = Url::fromModuleRoute('Messenger', 'groups_manage_edit.php')->withQueryParams(['gibbonGroupID' => $values['gibbonGroupID']]);
            return Format::link($url, __('View'));
        });

    $table->addColumn('teacherCount', __('Teachers'))
        ->width('8%');

    $table->addColumn('supportCount', __('Support'))
        ->width('8%');

    $table->addColumn('studentCount', __('Students'))
        ->width('8%');

    $table->addColumn('active', __('Active'))
        ->format(Format::using('yesNo', 'active'))
        ->width('10%');

    // ACTIONS
    $table->addActionColumn()
        ->addParam('search', $criteria->getSearchText(true))
        ->addParam('gibbonSchoolYearID', $params['gibbonSchoolYearID'])
        ->addParam('deepLearningExperienceID')
        ->format(function ($experience, $actions) use ($highestAction) {
            if ($highestAction == 'Manage Experiences_all' || (!empty($experience['canEdit']) && $experience['canEdit'] == 'Y')) {
                $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/Deep Learning/experience_manage_edit.php');
            }

            if ($highestAction == 'Manage Experiences_all') {
                $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/Deep Learning/experience_manage_delete.php')
                        ->modalWindow(650, 400);
            }
        });

    echo $table->render($experiences ?? []);
}
