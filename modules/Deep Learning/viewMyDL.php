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
use Gibbon\Module\DeepLearning\Domain\StaffGateway;

if (isActionAccessible($guid, $connection2, '/modules/Deep Learning/viewMyDL.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__m('My Deep Learning'));

    $page->return->addReturns([
        'success1' => __m('You have been successfully signed up for this Deep Learning event. You can view and manage your sign up below.'),
        'error4' => __m('Sign up is currently not available for this Deep Learning event.'),
        'error5' => __m('There was an error verifying your Deep Learning choices. Please try again.'),
    ]);

    $roleCategory = $session->get('gibbonRoleIDCurrentCategory');

    if ($roleCategory == 'Parent') {
        include 'viewDL.php';
        return;
    }

    $canSignUp = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_signUp');
    $canView = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php');
    $canViewInactive = isActionAccessible($guid, $connection2, '/modules/Deep Learning/view.php', 'Deep Learning Events_viewInactive');
    $canOverview = isActionAccessible($guid, $connection2, '/modules/Deep Learning/report_overview.php');
    $canManage = isActionAccessible($guid, $connection2, '/modules/Deep Learning/experience_manage.php');

    // Query events
    $eventGateway = $container->get(EventGateway::class);
    $enrolmentGateway = $container->get(EnrolmentGateway::class);
    $staffGateway = $container->get(StaffGateway::class);
    
    $criteria = $eventGateway->newQueryCriteria()
        ->sortBy(['startDate'])
        ->fromPOST();

    $events = $eventGateway->queryEventsByPerson($criteria, $session->get('gibbonSchoolYearID'), $session->get('gibbonPersonID'));
    $yearGroupCount = $container->get(YearGroupGateway::class)->getYearGroupCount();
    
    $events->transform(function (&$values) use (&$eventGateway, &$enrolmentGateway, &$staffGateway) {
        $event = $eventGateway->getEventDetailsByID($values['deepLearningEventID']);
        $staff = $staffGateway->selectStaffByEventAndPerson($values['deepLearningEventID'], $values['gibbonPersonID'])->fetchAll();
        $enrolment = $enrolmentGateway->getExperienceDetailsByEnrolment($values['deepLearningEventID'], $values['gibbonPersonID']);

        $values['staff'] = $staff ?? [];
        $values['enrolment'] = $enrolment ?? [];
        $values['yearGroups'] = $event['yearGroups'] ?? '';
        $values['yearGroupCount'] = $event['yearGroupCount'] ?? '';
        $values['signUpEvent'] = $eventGateway->getEventSignUpAccess($values['deepLearningEventID'], $values['gibbonPersonID']);
    });

    $table = DataTable::create('events');
    $table->setTitle(__m('My Deep Learning'));

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

    if ($roleCategory == 'Staff') {
        $table->addColumn('choices', __m('Experience'))
        ->context('primary')
        ->width('30%')
        ->format(function ($values) use ($canView) {
            if (empty($values['staff'])) return '';
            $output = '';

            foreach ($values['staff'] as $staff) {
                $url = Url::fromModuleRoute('Deep Learning', 'view_experience.php')->withQueryParams(['deepLearningEventID' => $values['deepLearningEventID'], 'deepLearningExperienceID' => $staff['deepLearningExperienceID'], 'sidebar' => 'false']);
                $output .= $canView
                    ? Format::link($url, $staff['name']).'<br/>'.Format::small($staff['role']).'<br/>'
                    : $staff['name'].'<br/>'.Format::small($staff['role']).'<br/>';
            }

            return $output;
        });
    }
    elseif ($roleCategory == 'Student') {
        $table->addColumn('choices', __m('Experience'))
            ->context('primary')
            ->width('30%')
            ->format(function ($values) {
                $now = (new DateTime('now'))->format('U');
                $accessEnrolmentDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessEnrolmentDate']);
                if ($accessEnrolmentDate && $accessEnrolmentDate->format('U') < $now) {
                    if (!empty($values['enrolment'])) {
                        $url = Url::fromModuleRoute('Deep Learning', 'view_experience.php')->withQueryParams(['deepLearningEventID' => $values['deepLearningEventID'], 'deepLearningExperienceID' => $values['enrolment']['deepLearningExperienceID'], 'sidebar' => 'false']);
                        return Format::small(__m('Enrolled in')).':<br>'.Format::link($url, $values['enrolment']['name']);
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
                    return $canSignUp && !empty($values['signUpEvent'])
                        ? Format::tag(__m('Sign up is open'), 'success')
                        : Format::tag(__m('Sign up is open for').' '.$values['yearGroups'], 'success');
                }

                if ($accessOpenDate && $accessOpenDate->format('U') > $now) {
                    return Format::tag(__m('Sign up opens on').'<br/>'.$accessOpenDate->format('M j \\a\\t g:ia'), 'message');
                }

                return Format::tag(__m('Upcoming Event'), 'dull');
            });
    }

    // ACTIONS
    $table->addActionColumn()
        ->addParam('deepLearningEventID')
        ->format(function ($values, $actions) use ($canSignUp, $roleCategory, $canView, $canOverview, $canManage) {
            if ($roleCategory == 'Staff') {
                $staff = !empty($values['staff']) ? current($values['staff']) : [];

                if ($canOverview) {
                    $actions->addAction('view', __('View'))
                            ->setURL('/modules/Deep Learning/report_overview.php')
                            ->addParam('deepLearningExperienceID', $staff['deepLearningExperienceID'] ?? '');
                }

                if ($canManage && count($values['staff']) == 1 && $staff['canEdit'] == 'Y') {
                    $actions->addAction('edit', __('Edit'))
                            ->setURL('/modules/Deep Learning/experience_manage_edit.php')
                            ->addParam('deepLearningExperienceID', $staff['deepLearningExperienceID'] ?? '');
                } elseif ($canManage && count($values['staff']) > 1) {
                    $actions->addAction('edit', __('Edit'))
                            ->setURL('/modules/Deep Learning/experience_manage.php');
                }

                if ($canManage && count($values['staff']) == 1 && !empty($staff['gibbonGroupID'])) {
                    $actions->addAction('attendance', __('Attendance'))
                            ->setURL('/modules/Attendance/attendance_take_adHoc.php')
                            ->setIcon('attendance')
                            ->addParams(['gibbonGroupID' => $staff['gibbonGroupID'], 'target' => 'Messenger', 'currentDate' => Format::date(date('Y-m-d'))]);
                }
            }
            elseif ($roleCategory == 'Student') {
                if (empty($values['signUpEvent'])) {
                    return '';
                }

                // Check that sign up is open based on the date
                $signUpIsOpen = false;
                $viewable = $values['viewable'] == 'Y';

                if (!empty($values['accessOpenDate']) && !empty($values['accessCloseDate'])) {
                    $accessOpenDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessOpenDate'])->format('U');
                    $accessCloseDate = DateTime::createFromFormat('Y-m-d H:i:s', $values['accessCloseDate'])->format('U');
                    $now = (new DateTime('now'))->format('U');

                    $signUpIsOpen = $accessOpenDate <= $now && $accessCloseDate >= $now;
                }

                if ($viewable && $signUpIsOpen && $canSignUp) {
                    $actions->addAction('add', __('Sign Up'))
                            ->setURL('/modules/Deep Learning/view_experience_signUp.php')
                            ->modalWindow(750, 440);
                }
            }
        });

    echo $table->render($events);
}
