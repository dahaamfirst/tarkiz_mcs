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
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Module\DeepLearning\Domain\EventGateway;
use Gibbon\Module\DeepLearning\Domain\EnrolmentGateway;
use Gibbon\Domain\Students\StudentGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/viewDL.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('View Deep Learning'));

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    $canView = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php');
    $yearGroupCount = $container->get(YearGroupGateway::class)->getYearGroupCount();

    // Query events
    $eventGateway = $container->get(EventGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $studentGateway = $container->get(StudentGateway::class);

    $children = $studentGateway->selectActiveStudentsByFamilyAdult($gibbonSchoolYearID, $gibbonPersonID)->fetchAll();

    if (empty($children)) {
        echo Format::alert(__('There are no records to display.'), 'message');
        return;
    }


    foreach ($children as $child) {
        
        $criteria = $eventGateway->newQueryCriteria()
            ->sortBy(['startDate'])
            ->fromPOST();

        $events = $eventGateway->queryEventsByPerson($criteria, $gibbonSchoolYearID, $child['gibbonPersonID'], true);

        $events->transform(function (&$values) use (&$eventGateway, &$enrolmentGateway, &$staffGateway) {
            $event = $eventGateway->getEventDetailsByID($values['deepLearningEventID']);
            $enrolment = $enrolmentGateway->getExperienceDetailsByEnrolment($values['deepLearningEventID'], $values['gibbonPersonID']);

            $values['enrolment'] = $enrolment ?? [];
            $values['yearGroups'] = $event['yearGroups'] ?? '';
            $values['yearGroupCount'] = $event['yearGroupCount'] ?? '';
        });

        $table = DataTable::create('events');
        $table->setTitle(Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true));

        $table->addColumn('name', __('Event'))
            ->sortable(['deepLearningEvent.name'])
            ->context('primary')
            ->width('20%')
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
            ->context('secondary')
            ->width('20%')
            ->format(function ($values) {
                $dates = array_map(function ($date) {
                    return Format::dateReadable($date);
                }, explode(',', $values['eventDates'] ?? ''));

                return implode('<br/>', $dates);
            });


            $table->addColumn('choices', __m('Experience'))
                ->context('primary')
                ->width('40%')
                ->format(function ($values) {
                    $now = (new DateTime('now'))->format('U');
                    $accessEnrolmentDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessEnrolmentDate']);
                    if ($accessEnrolmentDate && $accessEnrolmentDate->format('U') < $now) {
                        if (!empty($values['enrolment'])) {
                            $url = Url::fromModuleRoute('Deep Learning', 'view_experience.php')->withQueryParams(['deepLearningEventID' => $values['deepLearningEventID'], 'deepLearningExperienceID' => $values['enrolment']['deepLearningExperienceID'], 'sidebar' => 'false']);
                            return Format::small(__m('Enrolled in')).':<br>'.Format::link($url, $values['enrolment']['name']);
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
                ->width('20%')
                ->format(function ($values) {
                    if ($values['viewable'] != 'Y') {
                        return;
                    }

                    $now = (new DateTime('now'))->format('U');

                    $endDate = DateTime::createFromFormat('Y-m-d', $values['endDate'] ?? '');
                    if ($endDate && $endDate->format('U') < $now) {
                        return Format::tag(__m('Past'), 'dull');
                    }

                    $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessCloseDate']);
                    if ($accessCloseDate && $accessCloseDate->format('U') < $now) {
                        return Format::tag(__m('Sign up closed on').'<br/>'.$accessCloseDate->format('M j \\a\\t g:ia'), 'dull');
                    }

                    $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessOpenDate']);
                    if ($accessOpenDate && ($accessOpenDate->format('U') <= $now && $accessCloseDate->format('U') >= $now)) {
                        return Format::tag(__m('Sign up is open for').' '.$values['yearGroups'], 'success');
                    }

                    if ($accessOpenDate && $accessOpenDate->format('U') > $now) {
                        return Format::tag(__m('Sign up opens on').'<br/>'.$accessOpenDate->format('M j \\a\\t g:ia'), 'message');
                    }

                    return Format::tag(__m('Upcoming Event'), 'dull');
                });

        echo $table->render($events);
    }
}
