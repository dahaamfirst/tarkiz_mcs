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

class ExperienceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningExperience';
    private static $primaryKey = 'deepLearningExperienceID';
    private static $searchableColumns = ['deepLearningExperience.name', 'deepLearningEvent.name', 'deepLearningEvent.nameShort', 'deepLearningUnit.name'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryExperiences(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name as eventName',
                'deepLearningEvent.nameShort as eventNameShort',
                'deepLearningEvent.accessOpenDate',
                'deepLearningEvent.accessCloseDate',
                "(CASE WHEN CURRENT_TIMESTAMP >= deepLearningEvent.viewableDate THEN 'Y' ELSE 'N' END) as viewable",
                'deepLearningExperience.deepLearningExperienceID',
                'deepLearningExperience.name',
                'deepLearningExperience.active',
                'deepLearningExperience.gibbonGroupID',
                "REPLACE(GROUP_CONCAT(DISTINCT gibbonYearGroup.nameShort ORDER BY gibbonYearGroup.sequenceNumber SEPARATOR ', '),'Y0','Y') AS yearGroups",
                "COUNT(DISTINCT gibbonYearGroup.gibbonYearGroupID) as yearGroupCount",
                "GROUP_CONCAT(DISTINCT CONCAT(gibbonPerson.preferredName, ' ', gibbonPerson.surname) ORDER BY gibbonPerson.surname SEPARATOR '<br/>') as tripLeaders",
                "COUNT(DISTINCT CASE WHEN deepLearningStaff.role <> 'Support' THEN deepLearningStaff.deepLearningStaffID END) as teacherCount",
                "COUNT(DISTINCT CASE WHEN deepLearningStaff.role = 'Support' THEN deepLearningStaff.deepLearningStaffID END) as supportCount",
                "(SELECT COUNT(*) FROM deepLearningEnrolment WHERE deepLearningEnrolment.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND status='Confirmed') as studentCount",
            ])
            ->from($this->getTableName())
            ->innerJoin('deepLearningEvent', 'deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID')
            ->leftJoin('gibbonYearGroup', 'FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningExperience.gibbonYearGroupIDList)')
            ->leftJoin('deepLearningUnit', 'deepLearningUnit.deepLearningUnitID=deepLearningExperience.deepLearningUnitID')
            ->leftJoin('deepLearningStaff', 'deepLearningStaff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID')
            ->leftJoin('deepLearningStaff as tripLeader', 'tripLeader.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND tripLeader.role="Trip Leader"')
            ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=tripLeader.gibbonPersonID')
            ->where('deepLearningEvent.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['deepLearningExperience.deepLearningExperienceID']);

        if (!empty($gibbonPersonID)) {
            $query->cols(['staff.canEdit', 'staff.role'])
                ->innerJoin('deepLearningStaff as staff', 'staff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID')
                ->where('staff.gibbonPersonID=:gibbonPersonID')
                ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        $criteria->addFilterRules([
            'event' => function ($query, $deepLearningEventID) {
                return $query
                    ->where('deepLearningEvent.deepLearningEventID = :deepLearningEventID')
                    ->bindValue('deepLearningEventID', $deepLearningEventID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param QueryCriteria $criteria
     * @param string $deepLearningEventID
     * @return DataSet
     */
    public function queryExperiencesByEvent(QueryCriteria $criteria, $deepLearningEventID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->cols([
                'deepLearningEvent.deepLearningEventID',
                'deepLearningEvent.name as eventName',
                'deepLearningEvent.nameShort as eventNameShort',
                'deepLearningEvent.accessOpenDate',
                'deepLearningEvent.accessCloseDate',
                'deepLearningExperience.deepLearningExperienceID',
                'deepLearningExperience.name',
                'deepLearningExperience.active',
                'deepLearningExperience.gibbonGroupID',
                'deepLearningUnit.enrolmentMin',
                'deepLearningUnit.enrolmentMax',
                'deepLearningUnit.headerImage',
            ])
            ->from($this->getTableName())
            ->innerJoin('deepLearningEvent', 'deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID')
            ->leftJoin('deepLearningUnit', 'deepLearningUnit.deepLearningUnitID=deepLearningExperience.deepLearningUnitID')
            ->where('deepLearningExperience.deepLearningEventID=:deepLearningEventID')
            ->bindValue('deepLearningEventID', $deepLearningEventID);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('deepLearningExperience.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function syncExperienceMessengerGroup($deepLearningExperienceID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "DELETE `gibbonGroupPerson` 
            FROM `gibbonGroupPerson` 
            JOIN deepLearningExperience ON (deepLearningExperience.gibbonGroupID=gibbonGroupPerson.gibbonGroupID)
            LEFT JOIN deepLearningEnrolment ON (deepLearningEnrolment.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND gibbonGroupPerson.gibbonPersonID=deepLearningEnrolment.gibbonPersonID)
            LEFT JOIN deepLearningStaff ON (deepLearningStaff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND gibbonGroupPerson.gibbonPersonID=deepLearningStaff.gibbonPersonID)
            WHERE deepLearningExperience.deepLearningExperienceID=:deepLearningExperienceID
            AND deepLearningExperience.gibbonGroupID IS NOT NULL
            AND deepLearningEnrolment.deepLearningEnrolmentID IS NULL
            AND deepLearningStaff.deepLearningStaffID IS NULL
        ";

        $this->db()->statement($sql, $data);

        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "INSERT INTO `gibbonGroupPerson` (`gibbonGroupID`, `gibbonPersonID`) 
            SELECT deepLearningExperience.gibbonGroupID, deepLearningEnrolment.gibbonPersonID
            FROM deepLearningExperience
            JOIN deepLearningEnrolment ON (deepLearningEnrolment.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID AND deepLearningEnrolment.status='Confirmed')
            LEFT JOIN gibbonGroupPerson as groupPerson ON (groupPerson.gibbonGroupID=deepLearningExperience.gibbonGroupID AND groupPerson.gibbonPersonID=deepLearningEnrolment.gibbonPersonID)
            WHERE deepLearningExperience.deepLearningExperienceID=:deepLearningExperienceID
            AND deepLearningExperience.gibbonGroupID IS NOT NULL
            AND groupPerson.gibbonGroupPersonID IS NULL
        ";

        $this->db()->statement($sql, $data);

        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "INSERT INTO `gibbonGroupPerson` (`gibbonGroupID`, `gibbonPersonID`) 
            SELECT deepLearningExperience.gibbonGroupID, deepLearningStaff.gibbonPersonID
            FROM deepLearningExperience
            JOIN deepLearningStaff ON (deepLearningStaff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID)
            LEFT JOIN gibbonGroupPerson as groupPerson ON (groupPerson.gibbonGroupID=deepLearningExperience.gibbonGroupID AND groupPerson.gibbonPersonID=deepLearningStaff.gibbonPersonID)
            WHERE deepLearningExperience.deepLearningExperienceID=:deepLearningExperienceID
            AND deepLearningExperience.gibbonGroupID IS NOT NULL
            AND groupPerson.gibbonGroupPersonID IS NULL
        ";

        return $this->db()->statement($sql, $data);
    }

    public function selectExperiences()
    {
        $sql = "SELECT deepLearningEvent.name as eventName, deepLearningEvent.deepLearningEventID, deepLearningExperience.deepLearningExperienceID, deepLearningExperience.name 
                FROM deepLearningExperience 
                JOIN deepLearningEvent ON (deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID)
                ORDER BY deepLearningEvent.name, deepLearningExperience.name";

        return $this->db()->select($sql);
    }

    public function selectExperiencesByEvent($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID];
        $sql = "SELECT deepLearningExperienceID as value, name 
                FROM deepLearningExperience 
                WHERE deepLearningEventID=:deepLearningEventID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function selectExperiencesByEventAndPerson($deepLearningEventID, $gibbonPersonID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT deepLearningExperience.deepLearningExperienceID as value, deepLearningExperience.name 
                FROM deepLearningExperience 
                JOIN deepLearningEvent ON (deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID)
                JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningExperience.gibbonYearGroupIDList))
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID)
                WHERE deepLearningExperience.deepLearningEventID=:deepLearningEventID
                AND gibbonStudentEnrolment.gibbonPersonID=:gibbonPersonID
                GROUP BY deepLearningExperience.deepLearningExperienceID
                ORDER BY deepLearningExperience.name";

        return $this->db()->select($sql, $data);
    }

    public function selectExperienceDetailsByEvent($deepLearningEventID)
    {
        $data = ['deepLearningEventID' => $deepLearningEventID];
        $sql = "SELECT deepLearningExperience.deepLearningExperienceID as groupBy, deepLearningExperience.*, 
                    deepLearningUnit.enrolmentMin, deepLearningUnit.enrolmentMax,
                    enrolment.count as enrolmentCount,
                    deepLearningUnit.location, deepLearningUnit.cost, deepLearningUnit.provider, deepLearningUnit.majors, deepLearningUnit.minors
                FROM deepLearningExperience 
                JOIN deepLearningUnit ON (deepLearningUnit.deepLearningUnitID=deepLearningExperience.deepLearningUnitID)
                LEFT JOIN (
                    SELECT COUNT(DISTINCT deepLearningEnrolment.deepLearningEnrolmentID) as count, deepLearningEnrolment.deepLearningExperienceID FROM deepLearningEnrolment
                    JOIN gibbonPerson ON (deepLearningEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                    WHERE gibbonPerson.status='Full' AND deepLearningEnrolment.status='Confirmed'
                    GROUP BY deepLearningEnrolment.deepLearningExperienceID
                ) as enrolment ON (enrolment.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID)
                WHERE deepLearningExperience.deepLearningEventID=:deepLearningEventID
                AND deepLearningExperience.active='Y'
                GROUP BY deepLearningExperience.deepLearningExperienceID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function getExperienceDetailsByID($deepLearningExperienceID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID];
        $sql = "SELECT 
                    deepLearningEvent.deepLearningEventID,
                    deepLearningEvent.name as eventName,
                    deepLearningEvent.nameShort as eventNameShort,
                    deepLearningEvent.accessOpenDate,
                    deepLearningEvent.accessCloseDate,
                    deepLearningEvent.gibbonSchoolYearID,
                    deepLearningExperience.*,
                    deepLearningUnit.headerImage,
                    deepLearningUnit.description,
                    deepLearningUnit.majors,
                    deepLearningUnit.minors,
                    deepLearningUnit.cost,
                    deepLearningUnit.location,
                    deepLearningUnit.provider,
                    deepLearningUnit.enrolmentMin,
                    deepLearningUnit.enrolmentMax,
                    REPLACE(GROUP_CONCAT(DISTINCT gibbonYearGroup.nameShort ORDER BY gibbonYearGroup.sequenceNumber SEPARATOR ', '),'Y0','Y') AS yearGroups,
                    COUNT(DISTINCT gibbonYearGroup.gibbonYearGroupID) as yearGroupCount
                FROM deepLearningExperience
                JOIN deepLearningEvent ON (deepLearningEvent.deepLearningEventID=deepLearningExperience.deepLearningEventID)
                LEFT JOIN deepLearningUnit ON (deepLearningUnit.deepLearningUnitID=deepLearningExperience.deepLearningUnitID)
                LEFT JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningExperience.gibbonYearGroupIDList))
                WHERE deepLearningExperience.deepLearningExperienceID=:deepLearningExperienceID
                GROUP BY deepLearningExperience.deepLearningExperienceID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getExperienceEditAccess($deepLearningExperienceID, $gibbonPersonID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT deepLearningStaff.canEdit
                FROM deepLearningExperience
                JOIN deepLearningStaff ON (deepLearningStaff.deepLearningExperienceID=deepLearningExperience.deepLearningExperienceID)
                WHERE deepLearningExperience.deepLearningExperienceID=:deepLearningExperienceID
                AND deepLearningStaff.gibbonPersonID=:gibbonPersonID
                GROUP BY deepLearningExperience.deepLearningExperienceID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getNextExperienceByID($deepLearningEventID, $deepLearningExperienceID)
    {
        $data = array('deepLearningEventID' => $deepLearningEventID, 'deepLearningExperienceID' => $deepLearningExperienceID);
        $sql = "SELECT * FROM deepLearningExperience WHERE name=(SELECT MIN(name) FROM deepLearningExperience WHERE name > (SELECT name FROM deepLearningExperience WHERE deepLearningExperienceID=:deepLearningExperienceID AND deepLearningEventID=:deepLearningEventID) AND deepLearningEventID=:deepLearningEventID) AND deepLearningEventID=:deepLearningEventID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getPreviousExperienceByID($deepLearningEventID, $deepLearningExperienceID)
    {
        $data = array('deepLearningEventID' => $deepLearningEventID, 'deepLearningExperienceID' => $deepLearningExperienceID);
        $sql = "SELECT * FROM deepLearningExperience WHERE name=(SELECT MAX(name) FROM deepLearningExperience WHERE name < (SELECT name FROM deepLearningExperience WHERE deepLearningExperienceID=:deepLearningExperienceID AND deepLearningEventID=:deepLearningEventID) AND deepLearningEventID=:deepLearningEventID) AND deepLearningEventID=:deepLearningEventID";

        return $this->db()->selectOne($sql, $data);
    }

    public function getExperienceSignUpAccess($deepLearningExperienceID, $gibbonPersonID)
    {
        $data = ['deepLearningExperienceID' => $deepLearningExperienceID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonStudentEnrolment.gibbonStudentEnrolmentID
                FROM deepLearningExperience
                JOIN deepLearningEvent ON (deepLearningExperience.deepLearningEventID=deepLearningEvent.deepLearningEventID)
                JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, deepLearningExperience.gibbonYearGroupIDList))
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonSchoolYearID=deepLearningEvent.gibbonSchoolYearID AND gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID)
                WHERE deepLearningExperience.deepLearningExperienceID=:deepLearningExperienceID
                AND gibbonStudentEnrolment.gibbonPersonID=:gibbonPersonID
                GROUP BY deepLearningExperience.deepLearningExperienceID";

        return $this->db()->selectOne($sql, $data);
    }
}
