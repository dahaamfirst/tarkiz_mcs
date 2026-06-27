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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Http\Url;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Module\DeepLearning\Domain\StaffGateway;
use Gibbon\Domain\Students\StudentGateway;

global $gibbon, $container;

//Module includes
require_once './modules/Free Learning/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // include_once $session->get('absolutePath').'/modules/Free Learning/src/Tables/UnitHistory.php';
    // include_once $session->get('absolutePath').'/modules/Free Learning/src/Domain/UnitStudentGateway.php';

    // $page->stylesheets->add('module-freeLearning', 'modules/Free Learning/css/module.css');
    // echo $container->get(UnitHistory::class)->create($gibbonPersonID);

    include_once $session->get('absolutePath').'/modules/Deep Learning/src/Domain/EventGateway.php';
    include_once $session->get('absolutePath').'/modules/Deep Learning/src/Domain/EnrolmentGateway.php';

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    $canSignUp = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_signUp');
    $canView = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php');
    $canViewInactive = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_viewInactive');
    $canOverview = isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_overview.php');
    $canManage = isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage.php');

    // Query events
    $enrolments = $container->get(StudentGateway::class)->selectAllStudentEnrolmentsByPerson($gibbonPersonID)->fetchAll();
    $yearGroupCount = $container->get(YearGroupGateway::class)->getYearGroupCount();

    $eventGateway = $container->get(EventGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);

    foreach ($enrolments as $enrolment) {
    
        $criteria = $eventGateway->newQueryCriteria()
            ->sortBy(['startDate'])
            ->fromPOST();

        $events = $eventGateway->queryEventsByPerson($criteria, $enrolment['gibbonSchoolYearID'], $gibbonPersonID, $enrolment['gibbonSchoolYearID'] == $session->get('gibbonSchoolYearID'));
        
        
        $events->transform(function (&$values) use (&$eventGateway, &$enrolmentGateway) {
            $event = $eventGateway->getEventDetailsByID($values['deepLearningEventID']);
            $enrolment = $enrolmentGateway->getExperienceDetailsByEnrolment($values['deepLearningEventID'], $values['gibbonPersonID']);

            $values['enrolment'] = $enrolment ?? [];
            $values['yearGroups'] = $event['yearGroups'] ?? '';
            $values['yearGroupCount'] = $event['yearGroupCount'] ?? '';
            $values['signUpEvent'] = $eventGateway->getEventSignUpAccess($values['deepLearningEventID'], $values['gibbonPersonID']);
        });

        if (count($events) == 0) continue;

        $table = DataTable::create('events');
        $table->setTitle($enrolment['name']);

        $table->addColumn('name', __('Event'))
            ->sortable(['deepLearningEvent.name'])
            ->context('primary')
            ->format(function ($values) use ($canView) {
                $url = Url::fromModuleRoute('Deep Learning', 'view_event.php')->withQueryParams(['deepLearningEventID' => $values['deepLearningEventID'], 'sidebar' => 'false']);
                return $canView && $values['active'] == 'Y' && $values['viewable'] == 'Y' 
                    ? Format::bold(Format::link($url, $values['name']))
                    : Format::bold($values['name']);
            })
            ->formatDetails(function ($values) use ($yearGroupCount) {
                return Format::small($values['yearGroupCount'] >= $yearGroupCount ? __m('All Year Groups') : $values['yearGroups']);
            });

        $table->addColumn('dates', __('Dates'))
            ->sortable(['eventDates'])
            ->context('primary')
            ->format(function ($values) {
                $dates = array_map(function ($date) {
                    return Format::dateReadable($date);
                }, explode(',', $values['eventDates'] ?? ''));

                return implode('<br/>', $dates);
            });


        $table->addColumn('choices', __m('Experience'))
            ->context('primary')
            ->width('30%')
            ->format(function ($values) use ($enrolment, $gibbonSchoolYearID) {
                $now = (new DateTime('now'))->format('U');
                $accessEnrolmentDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessEnrolmentDate']);
                if ($accessEnrolmentDate && $accessEnrolmentDate->format('U') < $now) {
                    if (!empty($values['enrolment'])) {
                        $url = Url::fromModuleRoute('Deep Learning', 'view_experience.php')->withQueryParams(['deepLearningEventID' => $values['deepLearningEventID'], 'deepLearningExperienceID' => $values['enrolment']['deepLearningExperienceID'], 'sidebar' => 'false']);

                        return $enrolment['gibbonSchoolYearID'] == $gibbonSchoolYearID
                            ? Format::small(__m('Enrolled in')).':<br>'.Format::link($url, $values['enrolment']['name'])
                            : $values['enrolment']['name'];
                    } else {
                        return '';
                    }
                }

                if (empty($values['choices'])) {
                    return '';
                }
                
                $choices = explode(',', $values['choices']);
                return Format::small(__m('Signed up for')).':<br/>'.Format::list($choices, 'ol', 'ml-2 my-0 text-xs');
            });


        $table->addColumn('status', __('Status'))
            ->format(function ($values) use ($canSignUp) {
                if ($values['viewable'] != 'Y' || empty($values['signUpEvent'])) {
                    return;
                }

                if (empty($values['endDate']) && empty($values['startDate'])) {
                    return Format::tag(__m('Past'), 'dull');
                }

                $now = (new DateTime('now'))->format('U');

                $endDate = DateTime::createFromFormat('Y-m-d', $values['endDate'] ?? '');
                if ($endDate && $endDate->format('U') < $now) {
                    return Format::tag(__m('Past'), 'dull');
                }

                $startDate = DateTime::createFromFormat('Y-m-d', $values['startDate'] ?? '');
                if ($startDate && $now >= $startDate->format('U') && $endDate && $now <= $endDate->format('U')) {
                    return Format::tag(__m('Current Event'), 'message');
                }

                $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessCloseDate']);
                if ($accessCloseDate && $accessCloseDate->format('U') < $now) {
                    return Format::tag(__m('Sign up closed on').'<br/>'.$accessCloseDate->format('M j \\a\\t g:ia'), 'dull');
                }

                $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessOpenDate']);
                if ($accessOpenDate && ($accessOpenDate->format('U') <= $now && $accessCloseDate->format('U') >= $now)) {
                    return $canSignUp && !empty($values['signUpEvent'])
                        ? Format::tag(__m('Sign up is open'), 'success')
                        : Format::tag(__m('Sign up is open for').' '.$values['yearGroups'], 'success');
                }

                if ($accessOpenDate && $accessOpenDate->format('U') > $now) {
                    return Format::tag(__m('Sign up opens on').'<br/>'.$accessOpenDate->format('M j \\a\\t g:ia'), 'message');
                }

                return Format::tag(__m('Upcoming Event'), 'dull');
            });

        echo $table->render($events);
    }
}
