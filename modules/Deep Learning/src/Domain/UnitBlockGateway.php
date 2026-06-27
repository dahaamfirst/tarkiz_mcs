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

class UnitBlockGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'deepLearningUnitBlock';
    private static $primaryKey = 'deepLearningUnitBlockID';
    private static $searchableColumns = [''];

    public function selectBlocksByUnit($deepLearningUnitID)
    {
        $data = ['deepLearningUnitID' => $deepLearningUnitID];
        $sql = "SELECT deepLearningUnitBlock.deepLearningUnitBlockID, deepLearningUnitBlock.title, deepLearningUnitBlock.content, deepLearningUnitBlock.type
                FROM deepLearningUnitBlock
                WHERE deepLearningUnitBlock.deepLearningUnitID=:deepLearningUnitID
                ORDER BY deepLearningUnitBlock.sequenceNumber";

        return $this->db()->select($sql, $data);
    }
    
    public function deleteBlocksNotInList($deepLearningUnitID, $blockIDList)
    {
        $blockIDList = is_array($blockIDList) ? implode(',', $blockIDList) : $blockIDList;

        $data = ['deepLearningUnitID' => $deepLearningUnitID, 'blockIDList' => $blockIDList];
        $sql = "DELETE FROM deepLearningUnitBlock WHERE deepLearningUnitID=:deepLearningUnitID AND NOT FIND_IN_SET(deepLearningUnitBlockID, :blockIDList)";

        return $this->db()->delete($sql, $data);
    }
}
