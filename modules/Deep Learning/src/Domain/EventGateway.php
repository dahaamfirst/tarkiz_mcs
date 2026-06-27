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

namespace Gibbon\Module\DeepLearning\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class EventGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningEvent';
    private static $primaryKey = 'deepLearningEventID';
    private static $searchableColumns = ['deepLearningEvent.name','deepLearningEvent.nameShort'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryEvents(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name',
                'deepLearningEvent.nameShort',
                'deepLearningEvent.description',
                'deepLearningEvent.backgroundImage',
                'deepLearningEvent.active',
                'deepLearningEvent.viewableDate',
                'deepLearningEvent.accessOpenDate',
                'deepLearningEvent.accessCloseDate',
                'deepLearningEvent.accessEnrolmentDate',
                "MIN(deepLearningEventDate.eventDate) as startDate",
                "MAX(deepLearningEventDate.eventDate) as endDate",
                "(CASE WHEN CURRENT_TIMESTAMP >= deepLearningEvent.viewableDate THEN 'Y' ELSE 'N' END) as viewable",
                "COUNT(DISTINCT deepLearningExperience.deepLearningExperienceID) as experienceCount",
                "GROUP_CONCAT(DISTINCT deepLearningEventDate.eventDate SEPARATOR ',') AS eventDates",
                "GROUP_CONCAT(DISTINCT deepLearningExperience.name) AS experienceNames",
                "REPLACE(GROUP_CONCAT(DISTINCT gibbonYearGroup.nameShort ORDER BY gibbonYearGroup.sequenceNumber SEPARATOR ', '),'Y0','Y') AS yearGroups",
                "COUNT(DISTINCT gibbonYearGroup.gibbonYearGroupID) as yearGroupCount"
            ])
            ->leftJoin('deepLearningEventDate', 'deepLearningEvent.deepLearningEventID=deepLearningEventDate.deepLearningEventID')
            ->leftJoin('deepLearningExperience', 'deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID')
            ->leftJoin('gibbonYearGroup', 'FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningEvent.gibbonYearGroupIDList)')
            ->where('deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['deepLearningEventID','name']);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('deepLearningEvent.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryEventsByPerson(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID, $checkYearGroup = false)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                ':gibbonPersonID as gibbonPersonID',
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name',
                'deepLearningEvent.nameShort',
                'deepLearningEvent.description',
                'deepLearningEvent.backgroundImage',
                'deepLearningEvent.active',
                'deepLearningEvent.viewableDate',
                'deepLearningEvent.accessOpenDate',
                'deepLearningEvent.accessCloseDate',
                'deepLearningEvent.accessEnrolmentDate',
                "MIN(deepLearningEventDate.eventDate) as startDate",
                "MAX(deepLearningEventDate.eventDate) as endDate",
                "(CASE WHEN CURRENT_TIMESTAMP >= deepLearningEvent.viewableDate THEN 'Y' ELSE 'N' END) as viewable",
                "GROUP_CONCAT(DISTINCT deepLearningEventDate.eventDate SEPARATOR ',') AS eventDates",
                "GROUP_CONCAT(DISTINCT deepLearningExperience.name ORDER BY deepLearningChoice.choice SEPARATOR ',') as choices",

            ])
            ->leftJoin('deepLearningEventDate', 'deepLearningEvent.deepLearningEventID=deepLearningEventDate.deepLearningEventID')
            ->leftJoin('deepLearningChoice', 'deepLearningChoice.deepLearningEventID=deepLearningEvent.deepLearningEventID AND deepLearningChoice.gibbonPersonID=:gibbonPersonID')
            ->leftJoin('deepLearningExperience', 'deepLearningExperience.deepLearningExperienceID=deepLearningChoice.deepLearningExperienceID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('deepLearningEvent.viewableDate <= CURRENT_TIMESTAMP')
            ->where('deepLearningEvent.active ="Y" ')
            ->groupBy(['deepLearningEvent.deepLearningEventID']);
    
        if ($checkYearGroup) {
            $query->leftJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonPersonID=:gibbonPersonID')
                ->where('FIND_IN_SET(gibbonStudentEnrolment.gibbonYearGroupID, deepLearningEvent.gibbonYearGroupIDList)');
        }

        return $this->runQuery($query, $criteria);
    }

    public function selectAllEvents()
    {
        $sql = "SELECT gibbonSchoolYear.name as groupBy, deepLearningEvent.deepLearningEventID as value, deepLearningEvent.name 
                FROM deepLearningEvent
                JOIN deepLearningEventDate ON (deepLearningEventDate.deepLearningEventID=deepLearningEvent.deepLearningEventID)
                JOIN gibbonSchoolYear ON (deepLearningEvent.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID) 
                WHERE deepLearningEvent.active='Y'
                ORDER BY gibbonSchoolYear.sequenceNumber DESC, deepLearningEventDate.eventDate, deepLearningEvent.name";

        return $this->db()->select($sql);
    }

    public function selectEventsBySchoolYear($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT deepLearningEvent.deepLearningEventID as value, deepLearningEvent.name 
                FROM deepLearningEvent
                LEFT JOIN deepLearningEventDate ON (deepLearningEventDate.deepLearningEventID=deepLearningEvent.deepLearningEventID)
                WHERE deepLearningEvent.active='Y'
                AND deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY deepLearningEventDate.eventDate, deepLearningEvent.name";

        return $this->db()->select($sql, $data);
    }

    public function getNextActiveEvent($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'today' => date('Y-m-d')];
        $sql = "SELECT MIN(deepLearningEvent.deepLearningEventID)
                FROM deepLearningEvent
                JOIN deepLearningEventDate ON (deepLearningEventDate.deepLearningEventID=deepLearningEvent.deepLearningEventID)
                WHERE deepLearningEvent.active='Y'
                AND deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID
                AND deepLearningEventDate.eventDate>=:today
                GROUP BY deepLearningEvent.deepLearningEventID
                ORDER BY deepLearningEventDate.eventDate ASC, deepLearningEvent.name";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectYearGroupsByEvent($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID];
        $sql = "SELECT gibbonYearGroup.gibbonYearGroupID as `value`, gibbonYearGroup.name
                FROM deepLearningEvent
                JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningEvent.gibbonYearGroupIDList))
                WHERE deepLearningEvent.deepLearningEventID=:deepLearningEventID
                ORDER BY gibbonYearGroup.sequenceNumber, gibbonYearGroup.name";

        return $this->db()->select($sql, $data);
    }

    public function getEventDetailsByID($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID];
        $sql = "SELECT deepLearningEvent.*,
                    MIN(deepLearningEventDate.eventDate) as startDate,
                    MAX(deepLearningEventDate.eventDate) as endDate,
                    gibbonSchoolYear.name as schoolYear, 
                    (CASE WHEN CURRENT_TIMESTAMP >= deepLearningEvent.viewableDate THEN 'Y' ELSE 'N' END) as viewable,
                    GROUP_CONCAT(DISTINCT deepLearningEventDate.eventDate SEPARATOR ',') AS eventDates,
                    REPLACE(GROUP_CONCAT(DISTINCT gibbonYearGroup.nameShort ORDER BY gibbonYearGroup.sequenceNumber SEPARATOR ', '),'Y0','Y') AS yearGroups,
                    COUNT(DISTINCT gibbonYearGroup.gibbonYearGroupID) as yearGroupCount
                FROM deepLearningEvent
                JOIN gibbonSchoolYear ON (deepLearningEvent.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID) 
                LEFT JOIN deepLearningEventDate ON (deepLearningEvent.deepLearningEventID=deepLearningEventDate.deepLearningEventID)
                LEFT JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningEvent.gibbonYearGroupIDList))
                WHERE deepLearningEvent.deepLearningEventID=:deepLearningEventID
                GROUP BY deepLearningEvent.deepLearningEventID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getEventSignUpAccess($deepLearningEventID, $gibbonPersonID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonStudentEnrolment.gibbonStudentEnrolmentID
                FROM deepLearningEvent
                JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningEvent.gibbonYearGroupIDList))
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID)
                WHERE deepLearningEvent.deepLearningEventID=:deepLearningEventID
                AND gibbonStudentEnrolment.gibbonPersonID=:gibbonPersonID
                GROUP BY deepLearningEvent.deepLearningEventID";

        return $this->db()->selectOne($sql, $data);
    }
}
