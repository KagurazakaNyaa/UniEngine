<?php

define('INSIDE', true);

$_EnginePath = './';
include($_EnginePath.'common.php');
include_once($_EnginePath . 'modules/attackSimulator/_includes.php');

use UniEngine\Engine\Modules\Flights;
use UniEngine\Engine\Includes\Helpers\World;
use UniEngine\Engine\Modules\AttackSimulator;

loggedCheck();

includeLang('simulator');
$_Lang['rows'] = '';
$_Lang['SimResult'] = '';

$TechEquivalents = [
    1 => 109,
    2 => 110,
    3 => 111,
    4 => 120,
    5 => 121,
    6 => 122,
    7 => 125,
    8 => 126,
    9 => 199,
];
$TechCount = count($TechEquivalents);
$MaxACSSlots = ACS_MAX_JOINED_FLEETS + 1;
$MaxStringLength = 30;

if(!empty($_POST['spyreport']))
{
    $_POST['spyreport'] = json_decode(stripslashes($_POST['spyreport']), true);
    $_POST['def_techs'][1] = (isset($_POST['spyreport']['tech']) ? $_POST['spyreport']['tech'] : null);
    $_POST['def_techs'][1] = object_map(
        $_POST['def_techs'][1],
        function ($value, $key) use ($TechEquivalents) {
            $safeKey = intval($key, 10);

            return [
                $value,
                $TechEquivalents[$safeKey],
            ];
        }
    );
    $_POST['def_ships'][1] = (isset($_POST['spyreport']['ships']) ? $_POST['spyreport']['ships'] : null);
    $_POST['spyreport'] = null;
}

if(isset($_POST['simulate']) && $_POST['simulate'] == 'yes')
{
    $Calculate = true;

    $AttackingTechs = [];
    $DefendingTechs = [];
    $AttackersData = [];
    $DefendersData = [];
    $AttackingFleets = [];
    $DefendingFleets = [];

    $inputMappingGroups = [
        [
            'techInputKey' => 'atk_techs',
            'fleetInputKey' => 'atk_ships',
            'techsAccumulatorObject' => &$AttackingTechs,
            'fleetAccumulatorObject' => &$AttackingFleets,
            'usersAccumulatorObject' => &$AttackersData,
            'usernamePrefix' => $_Lang['Attacker_Txt'],
        ],
        [
            'techInputKey' => 'def_techs',
            'fleetInputKey' => 'def_ships',
            'techsAccumulatorObject' => &$DefendingTechs,
            'fleetAccumulatorObject' => &$DefendingFleets,
            'usersAccumulatorObject' => &$DefendersData,
            'usernamePrefix' => $_Lang['Defender_Txt'],
        ],
    ];

    foreach ($inputMappingGroups as $inputMappingGroup) {
        if (empty($_POST[$inputMappingGroup['techInputKey']])) {
            continue;
        }

        foreach ($_POST[$inputMappingGroup['techInputKey']] as $userSlotIdx => $userSlotData) {
            $userIdx = $userSlotIdx - 1;

            foreach ($userSlotData as $elementId => $elementValue) {
                if (!World\Elements\isTechnology($elementId)) {
                    continue;
                }
                if (!isset($inputMappingGroup['techsAccumulatorObject'][$userIdx])) {
                    $inputMappingGroup['techsAccumulatorObject'][$userIdx] = [];
                }

                $safeElementValue = intval($elementValue, 10);

                $inputMappingGroup['techsAccumulatorObject'][$userIdx][$elementId] = $safeElementValue;
            }

            $inputMappingGroup['usersAccumulatorObject'][$userIdx] = [
                'username' => "{$inputMappingGroup['usernamePrefix']}{$userSlotIdx}",
                'techs' => Array2String($inputMappingGroup['techsAccumulatorObject'][$userIdx]),
                'pos' => '0:0:0'
            ];
        }
    }

    foreach ($inputMappingGroups as $inputMappingGroup) {
        if (empty($_POST[$inputMappingGroup['fleetInputKey']])) {
            $Calculate = false;
            $BreakMSG = (
                $inputMappingGroup['fleetInputKey'] === 'atk_ships' ?
                    $_Lang['Break_noATKShips'] :
                    $_Lang['Break_noDEFShips']
            );

            break;
        }

        foreach ($_POST[$inputMappingGroup['fleetInputKey']] as $userSlotIdx => $userSlotData) {
            $userIdx = $userSlotIdx - 1;

            foreach ($userSlotData as $elementId => $elementValue) {
                $elementCount = str_replace([ '.', ',' ], '', $elementValue);
                $elementCount = substr($elementCount, 0, $MaxStringLength);
                $safeElementCount = floor($elementCount);

                if (
                    (
                        !World\Elements\isShip($elementId) &&
                        !World\Elements\isDefenseSystem($elementId)
                    ) ||
                    $safeElementCount <= 0
                ) {
                    continue;
                }
                if (!isset($inputMappingGroup['fleetAccumulatorObject'][$userIdx])) {
                    $inputMappingGroup['fleetAccumulatorObject'][$userIdx] = [];
                }

                $inputMappingGroup['fleetAccumulatorObject'][$userIdx][$elementId] = $safeElementCount;
            }

            if (empty($inputMappingGroup['fleetAccumulatorObject'][$userIdx])) {
                // Unset user's techs & details
                unset($inputMappingGroup['techsAccumulatorObject'][$userIdx]);
                unset($inputMappingGroup['usersAccumulatorObject'][$userIdx]);
            } else if (empty($inputMappingGroup['techsAccumulatorObject'][$userIdx])) {
                // Fill user techs (with zeros) & details
                $inputMappingGroup['techsAccumulatorObject'][$userIdx] = [];
                $inputMappingGroup['usersAccumulatorObject'][$userIdx] = [
                    'username' => "{$inputMappingGroup['usernamePrefix']}{$userSlotIdx}",
                    'techs' => Array2String($inputMappingGroup['techsAccumulatorObject'][$userIdx]),
                    'pos' => '0:0:0'
                ];
            }
        }
    }

    if(isset($AttackersData))
    {
        foreach($AttackersData as $UserTemp => $Data)
        {
            if(empty($AttackingFleets[$UserTemp]))
            {
                unset($AttackingFleets[$UserTemp]);
                unset($AttackingTechs[$UserTemp]);
                unset($AttackersData[$UserTemp]);
            }
        }
    }
    if(isset($DefendersData))
    {
        foreach($DefendersData as $UserTemp => $Data)
        {
            if(empty($DefendingFleets[$UserTemp]))
            {
                unset($DefendingFleets[$UserTemp]);
                unset($DefendingTechs[$UserTemp]);
                unset($DefendersData[$UserTemp]);
            }
        }
    }

    if(empty($AttackingFleets) OR (empty($DefendingFleets)))
    {
        if(empty($AttackingFleets))
        {
            $BreakMSG = $_Lang['Break_noATKShips'];
        }
        else if(empty($DefendingFleets))
        {
            $BreakMSG = $_Lang['Break_noDEFShips'];
        }
        $Calculate = false;
    }

    if (MORALE_ENABLED) {
        if (!empty($AttackingFleets)) {
            foreach ($AttackingFleets as $ThisUser => $ThisData) {
                $ThisMoraleLevel = intval($_POST['atk_morale'][($ThisUser + 1)]);
                $ThisMoraleLevel = keepInRange($ThisMoraleLevel, -100, 100);

                $AttackersData[$ThisUser]['morale'] = $ThisMoraleLevel;

                $moraleCombatModifiers = Flights\Utils\Modifiers\calculateMoraleCombatModifiers([
                    'moraleLevel' => $ThisMoraleLevel,
                ]);

                $AttackingTechs[$ThisUser] = array_merge(
                    $AttackingTechs[$ThisUser],
                    $moraleCombatModifiers
                );
            }
        }
        if (!empty($DefendingFleets)) {
            foreach ($DefendingFleets as $ThisUser => $ThisData) {
                $ThisMoraleLevel = intval($_POST['def_morale'][($ThisUser + 1)]);
                $ThisMoraleLevel = keepInRange($ThisMoraleLevel, -100, 100);

                $DefendersData[$ThisUser]['morale'] = $ThisMoraleLevel;

                $moraleCombatModifiers = Flights\Utils\Modifiers\calculateMoraleCombatModifiers([
                    'moraleLevel' => $ThisMoraleLevel,
                ]);

                $DefendingTechs[$ThisUser] = array_merge(
                    $DefendingTechs[$ThisUser],
                    $moraleCombatModifiers
                );
            }
        }
    }

    if($Calculate === true)
    {
        $Loop = 1;

        if(!isset($IncludeCombatEngine))
        {
            include($_EnginePath.'includes/CombatEngineAres.php');
            include($_EnginePath.'includes/functions/CreateBattleReport.php');
            $IncludeCombatEngine = true;
        }

        $SimData['atk_win'] = 0;
        $SimData['def_win'] = 0;
        $SimData['draw'] = 0;
        $SimData['rounds'] = 0;
        $SimData['max_rounds'] = 0;
        $SimData['min_rounds'] = 99;

        $SimData['total_lost_atk'] = [
            'met' => 0,
            'cry' => 0,
            'deu' => 0,
        ];
        $SimData['total_lost_def'] = [
            'met' => 0,
            'cry' => 0,
            'deu' => 0,
        ];
        $SimData['ship_lost_atk'] = 0;
        $SimData['ship_lost_def'] = 0;
        $SimData['ship_lost_atk_min'] = 99999999999999999999.0;
        $SimData['ship_lost_atk_max'] = 0;
        $SimData['ship_lost_def_min'] = 99999999999999999999.0;
        $SimData['ship_lost_def_max'] = 0;

        $TotalTime = 0;
        for($i = 1; $i <= $Loop; $i += 1)
        {
            $Temp['ship_lost_atk'] = 0;
            $Temp['ship_lost_def'] = 0;

            $StartTime = microtime(true);

            // Now start Combat calculations
            $Combat = Combat($AttackingFleets, $DefendingFleets, $AttackingTechs, $DefendingTechs, true);

            $EndTime = microtime(true);
            $TimeNow = $EndTime - $StartTime;
            $TotalTime += $TimeNow;
            $totaltime = sprintf('%0.6f', $TimeNow);

            $RoundsData = $Combat['rounds'];

            $RoundCount = count($RoundsData) - 1;
            if($RoundCount > $SimData['max_rounds'])
            {
                $SimData['max_rounds'] = $RoundCount;
            }
            if($RoundCount < $SimData['min_rounds'])
            {
                $SimData['min_rounds'] = $RoundCount;
            }
            $SimData['rounds'] += $RoundCount;

            $Result = $Combat['result'];
            $AtkShips = $Combat['AttackerShips'];
            $DefShips = $Combat['DefenderShips'];
            $AtkLost = $Combat['AtkLose'];
            $DefLost = $Combat['DefLose'];
            $DefSysLost = $Combat['DefSysLost'];

            $debrisRecoveryPercentages = [
                'ships' => ($_GameConfig['Fleet_Cdr'] / 100),
                'defenses' => ($_GameConfig['Defs_Cdr'] / 100),
            ];

            // Calculate looses - attacker
            $attackersResourceLosses = Flights\Utils\Calculations\calculateResourcesLoss([
                'unitsLost' => $AtkLost,
                'debrisRecoveryPercentages' => $debrisRecoveryPercentages,
            ]);

            $RealDebrisMetalAtk = $attackersResourceLosses['realLoss']['metal'];
            $RealDebrisCrystalAtk = $attackersResourceLosses['realLoss']['crystal'];
            $RealDebrisDeuteriumAtk = $attackersResourceLosses['realLoss']['deuterium'];

            if (!empty($AtkLost)) {
                foreach ($AtkLost as $ID => $Count) {
                    if ($ID > 200 && $ID < 300) {
                        $SimData['ship_lost_atk'] += $Count;
                        $Temp['ship_lost_atk'] += $Count;
                    }
                }
            }

            $SimData['total_lost_atk']['met'] += $RealDebrisMetalAtk;
            $SimData['total_lost_atk']['cry'] += $RealDebrisCrystalAtk;
            $SimData['total_lost_atk']['deu'] += $RealDebrisDeuteriumAtk;

            // Calculate looses - defender
            $defendersResourceLosses = Flights\Utils\Calculations\calculateResourcesLoss([
                'unitsLost' => $DefLost,
                'debrisRecoveryPercentages' => $debrisRecoveryPercentages,
            ]);

            $RealDebrisMetalDef = $defendersResourceLosses['realLoss']['metal'];
            $RealDebrisCrystalDef = $defendersResourceLosses['realLoss']['crystal'];
            $RealDebrisDeuteriumDef = $defendersResourceLosses['realLoss']['deuterium'];

            if (!empty($DefLost)) {
                foreach ($DefLost as $ID => $Count) {
                    $SimData['ship_lost_def'] += $Count;
                    $Temp['ship_lost_def'] += $Count;
                }
            }

            $SimData['total_lost_def']['met'] += $RealDebrisMetalDef;
            $SimData['total_lost_def']['cry'] += $RealDebrisCrystalDef;
            $SimData['total_lost_def']['deu'] += $RealDebrisDeuteriumDef;

            // Calculate looses - total
            $TotalLostMetal = (
                $attackersResourceLosses['recoverableLoss']['metal'] +
                $defendersResourceLosses['recoverableLoss']['metal']
            );
            $TotalLostCrystal = (
                $attackersResourceLosses['recoverableLoss']['crystal'] +
                $defendersResourceLosses['recoverableLoss']['crystal']
            );

            switch($Result)
            {
                case COMBAT_ATK:
                    $SimData['atk_win'] += 1;
                    break;
                case COMBAT_DEF:
                    $SimData['def_win'] += 1;
                    break;
                case COMBAT_DRAW:
                    $SimData['draw'] +=1 ;
                    break;
            }

            switch($Result)
            {
                case COMBAT_ATK:
                    $_Lang['Winner_Color'] = 'red';
                    $_Lang['Winner_Name'] = $_Lang['WonBy_Attacker'];
                    break;
                case COMBAT_DEF:
                    $_Lang['Winner_Color'] = 'lime';
                    $_Lang['Winner_Name'] = $_Lang['WonBy_Defender'];
                    break;
                case COMBAT_DRAW:
                    $_Lang['Winner_Color'] = 'orange';
                    $_Lang['Winner_Name'] = $_Lang['WonBy_Draw'];
                    break;
            }

            $moonCreationRollResult = Flights\Utils\Calculations\calculateMoonCreationRoll([
                'totalDebris' => ($TotalLostMetal + $TotalLostCrystal),
            ]);

            $ReportData = [];

            $ReportData['init']['usr']['atk'] = $AttackersData;
            $ReportData['init']['usr']['def'] = $DefendersData;

            $ReportData['init']['time'] = $totaltime;
            $ReportData['init']['date'] = time();

            $ReportData['init']['result'] = $Result;
            $ReportData['init']['met'] = 0;
            $ReportData['init']['cry'] = 0;
            $ReportData['init']['deu'] = 0;
            $ReportData['init']['deb_met'] = $TotalLostMetal;
            $ReportData['init']['deb_cry'] = $TotalLostCrystal;
            $ReportData['init']['moon_chance'] = $moonCreationRollResult['boundedMoonChance'];
            $ReportData['init']['total_moon_chance'] = $moonCreationRollResult['totalMoonChance'];
            $ReportData['init']['moon_created'] = false;
            $ReportData['init']['moon_destroyed'] = false;
            $ReportData['init']['moon_des_chance'] = '0';
            $ReportData['init']['fleet_destroyed'] = false;
            $ReportData['init']['fleet_des_chance'] = '0';
            $ReportData['init']['planet_name'] = 'Planeta';
            $ReportData['init']['onMoon'] = false;
            $ReportData['init']['atk_lost'] = $RealDebrisMetalAtk + $RealDebrisCrystalAtk + $RealDebrisDeuteriumAtk;
            $ReportData['init']['def_lost'] = $RealDebrisMetalDef + $RealDebrisCrystalDef + $RealDebrisDeuteriumDef;

            foreach($RoundsData as $RoundKey => $RoundData)
            {
                foreach($RoundData as $MainKey => $RoundData2)
                {
                    if(!empty($RoundData2['ships']))
                    {
                        foreach($RoundData2['ships'] as $UserKey => $UserData)
                        {
                            $RoundsData[$RoundKey][$MainKey]['ships'][$UserKey] = Array2String($UserData);
                        }
                    }
                }
            }
            $ReportData['rounds'] = $RoundsData;

            $ReportID = CreateBattleReport(
                $ReportData,
                [
                    'atk' => $_User['id'],
                    'def' => 0,
                ],
                0,
                true
            );

            $parse = $_Lang;
            $parse['id'] = $ReportID;

            $AllReports[] = $ReportID;
            if($i == $Loop)
            {
                $parse['time'] = sprintf('%0.6f', $TotalTime);
            }
            else
            {
                $parse['time'] = $totaltime;
            }

            if($Temp['ship_lost_atk'] < $SimData['ship_lost_atk_min'])
            {
                $SimData['ship_lost_atk_min'] = $Temp['ship_lost_atk'];
            }
            if($Temp['ship_lost_atk'] > $SimData['ship_lost_atk_max'])
            {
                $SimData['ship_lost_atk_max'] = $Temp['ship_lost_atk'];
            }

            if($Temp['ship_lost_def'] < $SimData['ship_lost_def_min'])
            {
                $SimData['ship_lost_def_min'] = $Temp['ship_lost_def'];
            }
            if($Temp['ship_lost_def'] > $SimData['ship_lost_def_max'])
            {
                $SimData['ship_lost_def_max'] = $Temp['ship_lost_def'];
            }
        }

        $SimData['ship_lost_def_max'] = prettyNumber($SimData['ship_lost_def_max']);
        $SimData['ship_lost_def_min'] = prettyNumber($SimData['ship_lost_def_min']);
        $SimData['ship_lost_atk_max'] = prettyNumber($SimData['ship_lost_atk_max']);
        $SimData['ship_lost_atk_min'] = prettyNumber($SimData['ship_lost_atk_min']);
        $SimData['total_lost_atk_met'] = prettyNumber(round($SimData['total_lost_atk']['met'] / $Loop));
        $SimData['total_lost_atk_cry'] = prettyNumber(round($SimData['total_lost_atk']['cry'] / $Loop));
        $SimData['total_lost_atk_deu'] = prettyNumber(round($SimData['total_lost_atk']['deu'] / $Loop));
        $SimData['total_lost_def_met'] = prettyNumber(round($SimData['total_lost_def']['met'] / $Loop));
        $SimData['total_lost_def_cry'] = prettyNumber(round($SimData['total_lost_def']['cry'] / $Loop));
        $SimData['total_lost_def_deu'] = prettyNumber(round($SimData['total_lost_def']['deu'] / $Loop));
        $SimData['ship_lost_atk'] = prettyNumber(round($SimData['ship_lost_atk'] / $Loop));
        $SimData['ship_lost_def'] = prettyNumber(round($SimData['ship_lost_def'] / $Loop));
        $SimData['rounds'] = round($SimData['rounds']/$Loop);

        $SimData['sim_loop'] = $Loop;
        $SimData['AddInfo'] = ((!empty($SimData['AddInfo'])) ? implode('<br/>', $SimData['AddInfo']).'<br/><br/>' : '');
        $parse = array_merge($parse, $SimData);

        $_Lang['SimResult'] .= parsetemplate(gettemplate('simulator_result'), $parse);

        // Trigger Tasks Check
        Tasks_TriggerTask($_User, 'USE_SIMULATOR');
    }
    else
    {
        $parse = $_Lang;
        $parse['msg'] = $BreakMSG;
        $_Lang['SimResult'] .= parsetemplate(gettemplate('simulator_result_warn'), $parse);
    }

}

$TPL_Slot = gettemplate('simulator_slot');
$TPL_SingleRow = gettemplate('simulator_single_row');
$TPL_NoBoth = gettemplate('simulator_row_noboth');
$TPL_Row = gettemplate('simulator_row');
$TPL_NoLeft = gettemplate('simulator_row_noleft');

for($i = 1; $i <= $MaxACSSlots; $i += 1)
{
    $ThisSlot = [];
    $ThisSlot['SlotID'] = $i;
    $ThisSlot['txt'] = '';

    $parse = $_Lang;
    $parse['i'] = $i;
    if($i > 1)
    {
        $ThisSlot['SlotHidden'] = 'hide';
    }

    if(MORALE_ENABLED)
    {
        $parse['RowText'] = $_Lang['Morale'];
        $ThisSlot['txt'] .= parsetemplate($TPL_SingleRow, $parse);
        $parse['RowText'] = $_Lang['Morale_Level'];
        $parse['RowInput'] = "<input type=\"text\" tabindex=\"1\" name=\"atk_morale[{$i}]\" value=\"{$_POST['atk_morale'][$i]}\" autocomplete=\"off\" />%";
        $parse['RowText2'] = $_Lang['Morale_Level'];
        $parse['RowInput2'] = "<input type=\"text\" tabindex=\"2\" name=\"def_morale[{$i}]\" value=\"{$_POST['def_morale'][$i]}\" autocomplete=\"off\" />%";

        $ThisSlot['txt'] .= parsetemplate($TPL_Row, $parse);
    }

    $parse['RowText'] = $_Lang['Technology'];
    $ThisSlot['txt'] .= parsetemplate($TPL_SingleRow, $parse);
    $parse['RowText'] = '<a class="orange point fillTech_atk">'.$_Lang['FillMyTechs'].'</a> / <a class="orange point clnTech_atk">'.$_Lang['Fill_Clean'].'</a>';
    $parse['RowText2'] = '<a class="orange point fillTech_def">'.$_Lang['FillMyTechs'].'</a> / <a class="orange point clnTech_def">'.$_Lang['Fill_Clean'].'</a>';
    $ThisSlot['txt'] .= parsetemplate($TPL_NoBoth, $parse);

    foreach ($TechEquivalents as $elementId) {
        $ThisRow_InsertValue_Atk = isset($_POST['atk_techs'][$i][$elementId]) ? $_POST['atk_techs'][$i][$elementId] : null;
        $ThisRow_InsertValue_Def = isset($_POST['def_techs'][$i][$elementId]) ? $_POST['def_techs'][$i][$elementId] : null;

        $parse['RowText'] = $_Lang['tech'][$elementId];
        $parse['RowInput'] = "<input type=\"text\" tabindex=\"1\" name=\"atk_techs[{$i}][{$elementId}]\" value=\"{$ThisRow_InsertValue_Atk}\" autocomplete=\"off\" />";
        $parse['RowText2'] = $_Lang['tech'][$elementId];
        $parse['RowInput2'] = "<input type=\"text\" tabindex=\"2\" name=\"def_techs[{$i}][{$elementId}]\" value=\"{$ThisRow_InsertValue_Def}\" autocomplete=\"off\" />";

        $ThisSlot['txt'] .= parsetemplate($TPL_Row, $parse);
    }

    $parse['RowText'] = $_Lang['Fleets'];
    $ThisSlot['txt'] .= parsetemplate($TPL_SingleRow, $parse);
    $parse['RowText'] = '<a class="orange point fillShip_atk">'.$_Lang['FillMyFleets'].'</a> / <a class="orange point clnShip_atk">'.$_Lang['Fill_Clean'].'</a>';
    $parse['RowText2'] = '<a class="orange point fillShip_def">'.$_Lang['FillMyFleets'].'</a> / <a class="orange point clnShip_def">'.$_Lang['Fill_Clean'].'</a>';
    $ThisSlot['txt'] .= parsetemplate($TPL_NoBoth, $parse);

    foreach($_Vars_ElementCategories['fleet'] as $elementId)
    {
        $ThisRow_InsertValue_Def = isset($_POST['def_ships'][$i][$elementId]) ? $_POST['def_ships'][$i][$elementId] : null;

        $parse['RowText2'] = $_Lang['tech'][$elementId];
        $parse['RowInput2'] = AttackSimulator\Components\ShipInput\render([
            'slotIdx' => $i,
            'elementId' => $elementId,
            'columnType' => 'defender',
            'initialValue' => $ThisRow_InsertValue_Def,
        ])['componentHTML'];

        if (hasAnyEngine($elementId)) {
            $ThisRow_InsertValue_Atk = isset($_POST['atk_ships'][$i][$elementId]) ? $_POST['atk_ships'][$i][$elementId] : null;

            $parse['RowText'] = $_Lang['tech'][$elementId];
            $parse['RowInput'] = AttackSimulator\Components\ShipInput\render([
                'slotIdx' => $i,
                'elementId' => $elementId,
                'columnType' => 'attacker',
                'initialValue' => $ThisRow_InsertValue_Atk,
            ])['componentHTML'];

            $ThisSlot['txt'] .= parsetemplate($TPL_Row, $parse);
        } else {
            $parse['RowText'] = '-';
            $parse['RowInput'] = '';

            $ThisSlot['txt'] .= parsetemplate($TPL_NoLeft, $parse);
        }
    }

    if ($i == 1) {
        $parse['RowText'] = $_Lang['Defense'];
        $ThisSlot['txt'] .= parsetemplate($TPL_SingleRow, $parse);

        foreach ($_Vars_ElementCategories['defense'] as $elementId) {
            if (World\Elements\isMissile($elementId)) {
                continue;
            }

            $ThisRow_InsertValue_Def = isset($_POST['def_ships'][$i][$elementId]) ? $_POST['def_ships'][$i][$elementId] : null;

            $parse['RowText'] = '-';
            $parse['RowInput'] = '';

            $parse['RowText2'] = $_Lang['tech'][$elementId];
            $parse['RowInput2'] = AttackSimulator\Components\ShipInput\render([
                'slotIdx' => $i,
                'elementId' => $elementId,
                'columnType' => 'defender',
                'initialValue' => $ThisRow_InsertValue_Def,
            ])['componentHTML'];

            $ThisSlot['txt'] .= parsetemplate($TPL_NoLeft, $parse);
        }
    }

    $_Lang['rows'] .= parsetemplate($TPL_Slot, $ThisSlot);
}

$isUsingPrettyInputs = ($_User['settings_useprettyinputbox'] == 1);

$ownTechLevels = object_map(
    $TechEquivalents,
    function ($elementId) use (&$_Planet, &$_User) {
        $currentLevel = World\Elements\getElementCurrentLevel($elementId, $_Planet, $_User);

        return [
            $currentLevel,
            $elementId
        ];
    }
);

$fleetsAndDefenses = array_filter(
    array_merge($_Vars_ElementCategories['fleet'], $_Vars_ElementCategories['defense']),
    function ($elementId) {
        return (
            World\Elements\isShip($elementId) ||
            World\Elements\isDefenseSystem($elementId)
        );
    }
);
$ownFleetsAndDefenses = object_map(
    $fleetsAndDefenses,
    function ($elementId) use (&$_Planet, &$_User, $isUsingPrettyInputs) {
        $currentCount = World\Elements\getElementCurrentCount($elementId, $_Planet, $_User);
        $currentCountDisplay = (
            $isUsingPrettyInputs ?
                prettyNumber($currentCount) :
                $currentCount
        );

        return [
            $currentCountDisplay,
            $elementId
        ];
    }
);

$_Lang['fill_with_mytechs'] = json_encode($ownTechLevels);
$_Lang['fill_with_myfleets'] = json_encode($ownFleetsAndDefenses);
$_Lang['AllowPrettyInputBox'] = ($isUsingPrettyInputs ? 'true' : 'false');

//Display page
$page = parsetemplate(gettemplate('simulator'), $_Lang);

display($page,$_Lang['Title'], false);

?>
