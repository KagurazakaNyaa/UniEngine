<?php

namespace UniEngine\Engine\Modules\FlightControl\Components\FlightsList\Utils;

use UniEngine\Engine\Includes\Helpers\Common\Collections;
use UniEngine\Engine\Modules\Flights;


// TODO: Reuse, same code as in FriendlyAcsList
function _getOwnFleetBehaviorDetails($params) {
    global $_Lang;

    $fleetEntry = $params['fleetEntry'];
    $currentTimestamp = $params['currentTimestamp'];

    if ($fleetEntry['fleet_start_time'] >= $currentTimestamp) {
        return [
            'behavior' => $_Lang['fl_get_to_ttl'],
            'behaviorTxt' => $_Lang['fl_get_to'],
        ];
    }

    if (
        $fleetEntry['fleet_end_stay'] > 0 &&
        $fleetEntry['fleet_end_stay'] > $currentTimestamp
    ) {
        $isMissionExpedition = $fleetEntry['fleet_mission'] == Flights\Enums\FleetMission::Expedition;

        return [
            'behavior' => (
                $isMissionExpedition ?
                    $_Lang['fl_explore_to_ttl'] :
                    $_Lang['fl_stay_to_ttl']
            ),
            'behaviorTxt' => (
                $isMissionExpedition ?
                    $_Lang['fl_explore_to'] :
                    $_Lang['fl_stay_to']
            ),
        ];
    }

    if ($fleetEntry['fleet_end_time'] > $currentTimestamp) {
        return [
            'behavior' => $_Lang['fl_back_to_ttl'],
            'behaviorTxt' => $_Lang['fl_back_to'],
        ];
    }

    return [
        'behavior' => $_Lang['fl_cameback_ttl'],
        'behaviorTxt' => $_Lang['fl_cameback'],
    ];
}

// TODO: this should most likely be a component
//  Arguments
//      - $params (Object)
//          - elementNo (Number)
//          - fleetEntry (Object)
//          - acsMainFleets (Record<mainFleetId: string, object>)
//          - currentTimestamp (Number)
//          - acsUnionsExtraSquads (Record<mainFleetId: string, object>)
//          - relatedAcsFleets (Array<object>)
//          - isJoiningThisUnion (Boolean)
//
function buildOwnListElement($params) {
    global $_Lang;

    $elementNo = $params['elementNo'];
    $fleetEntry = $params['fleetEntry'];
    $acsMainFleets = $params['acsMainFleets'];
    $currentTimestamp = $params['currentTimestamp'];
    $acsUnionsExtraSquads = $params['acsUnionsExtraSquads'];
    $relatedAcsFleets = $params['relatedAcsFleets'];
    $isJoiningThisUnion = $params['isJoiningThisUnion'];

    $fleetShipsRowTpl = gettemplate('fleet_fdetail');
    $fleetUnionSquadMainTpl = gettemplate('fleet_faddinfo');
    $fleetResourcesRowTpl = gettemplate('fleet_fresinfo');
    $fleetOrdersRetreatTpl = gettemplate('fleet_orders_retreat');
    $fleetOrdersCreateUnionTpl = gettemplate('fleet_orders_acs');
    $fleetOrdersJoinUnionTpl = gettemplate('fleet_orders_jointoacs');

    $fleetResourcesRowTpl = str_replace(
        [
            'TitleMain',
            'TitleMetal',
            'TitleCrystal',
            'TitleDeuterium',
        ],
        [
            $_Lang['fl_fleetinfo_resources'],
            $_Lang['Metal'],
            $_Lang['Crystal'],
            $_Lang['Deuterium'],
        ],
        $fleetResourcesRowTpl
    );

    $fleetId = $fleetEntry['fleet_id'];
    $fleetShips = String2Array($fleetEntry['fleet_array']);
    $fleetMissionType = $fleetEntry['fleet_mission'];
    $flightElapsedTime = ($currentTimestamp - $fleetEntry['fleet_send_time']);
    $flightTimeRemaining = ($fleetEntry['fleet_start_time'] - $currentTimestamp);
    $flightStayRemaining = ($fleetEntry['fleet_end_stay'] - $currentTimestamp);
    $flightComeBackRemaining = ($fleetEntry['fleet_end_time'] - $currentTimestamp);
    $isMainAcsFleet = !empty($acsMainFleets[$fleetId]);
    $hasUnionExtraSquad = !empty($acsUnionsExtraSquads[$fleetId]);
    $isFleetMissionNotCalculated = ($fleetEntry['fleet_mess'] == 0);
    $isNotCalculatedStationMission = (
        $fleetMissionType == Flights\Enums\FleetMission::Station &&
        $isFleetMissionNotCalculated
    );
    $isStillProtectingHoldMission = (
        !$isFleetMissionNotCalculated &&
        $fleetMissionType == Flights\Enums\FleetMission::Hold &&
        $flightStayRemaining > 0
    );
    $isRetreatStillAvailable = (
        $isFleetMissionNotCalculated ||
        $isStillProtectingHoldMission
    );

    $fleetMissionDisplayType = (
        (
            $isMainAcsFleet &&
            $acsMainFleets[$fleetId]['hasJoinedFleets']
        ) ?
            Flights\Enums\FleetMission::UnitedAttack :
            $fleetMissionType
    );

    $thisRelatedAcsFleetEntry = (
        $fleetMissionType == Flights\Enums\FleetMission::UnitedAttack ?
            array_find($relatedAcsFleets, function ($relatedAcsFleet) use ($fleetId) {
                return $fleetId == $relatedAcsFleet['fleetId'];
            }) :
            null
    );
    $supportingMainFleetId = (
        !empty($thisRelatedAcsFleetEntry) ?
            $thisRelatedAcsFleetEntry['mainFleetId'] :
            null
    );
    $thisUnionMainFleetId = (
        $isMainAcsFleet ?
            $fleetId :
            $supportingMainFleetId
    );

    $behaviorDetails = _getOwnFleetBehaviorDetails($params);
    $extraShipsInUnion = [];
    $extraShipsInUnionCount = 0;

    if ($hasUnionExtraSquad) {
        foreach ($acsUnionsExtraSquads[$fleetId] as $extraSquad) {
            if (empty($extraSquad['array'])) {
                continue;
            }

            foreach ($extraSquad['array'] as $shipId => $shipCount) {
                if (empty($extraShipsInUnion[$shipId])) {
                    $extraShipsInUnion[$shipId] = 0;
                }

                $extraShipsInUnion[$shipId] += $shipCount;
                $extraShipsInUnionCount += $shipCount;
            }
        }
    }

    $availableFleetOrders = Collections\compact([
        (
            $isRetreatStillAvailable ?
                parsetemplate(
                    $fleetOrdersRetreatTpl,
                    [
                        'FleetID' => $fleetEntry['fleet_id'],
                        'ButtonText' => (
                            $isFleetMissionNotCalculated ?
                                $_Lang['fl_sback'] :
                                $_Lang['fl_back_from_stay']
                        ),
                    ]
                ) :
                null
        ),
        (
            (
                $isFleetMissionNotCalculated &&
                $fleetMissionType == Flights\Enums\FleetMission::Attack
            ) ?
                parsetemplate(
                    $fleetOrdersCreateUnionTpl,
                    [
                        'FleetID' => $fleetEntry['fleet_id'],
                        'ButtonText' => $_Lang['fl_associate'],
                    ]
                ) :
                null
        ),
        (
            (
                $isFleetMissionNotCalculated &&
                !empty($acsMainFleets[$fleetId])
            ) ?
                parsetemplate(
                    $fleetOrdersJoinUnionTpl,
                    [
                        'ACS_ID' => $acsMainFleets[$fleetId]['acsId'],
                        'checked' => $isJoiningThisUnion,
                        'Text' => $_Lang['fl_acs_joinnow'],
                    ]
                ) :
                null
        ),
        (
            (
                $isFleetMissionNotCalculated &&
                empty($acsMainFleets[$fleetId])
            ) ?
                "{AddACSJoin_{$fleetId}}" :
                null
        ),
    ]);

    $thisUnionListNo = (
        $thisUnionMainFleetId !== null ?
        (array_search($thisUnionMainFleetId, array_keys($acsMainFleets)) + 1) :
        null
    );

    $fleetMissionSuffix = (
        $thisUnionListNo !== null ?
            " #{$thisUnionListNo}" :
            ""
    );

    $listElement = [
        'FleetNo'               => $elementNo,
        'FleetMissionColor'     => (
            $isMainAcsFleet ?
            'orange' :
            ''
        ),
        'FleetMission'          => (
            $_Lang['type_mission'][$fleetMissionDisplayType] .
            $fleetMissionSuffix
        ),
        'FleetBehaviour'        => $behaviorDetails['behavior'],
        'FleetBehaviourTxt'     => $behaviorDetails['behaviorTxt'],
        'FleetCount'            => prettyNumber($fleetEntry['fleet_amount'] + $extraShipsInUnionCount),
        'FleetResInfo'          => parsetemplate(
            $fleetResourcesRowTpl,
            [
                'FleetMetal' => prettyNumber($fleetEntry['fleet_resource_metal']),
                'FleetCrystal' => prettyNumber($fleetEntry['fleet_resource_crystal']),
                'FleetDeuterium' => prettyNumber($fleetEntry['fleet_resource_deuterium']),
            ]
        ),
        // Origin details
        'FleetOriGalaxy'        => $fleetEntry['fleet_start_galaxy'],
        'FleetOriSystem'        => $fleetEntry['fleet_start_system'],
        'FleetOriPlanet'        => $fleetEntry['fleet_start_planet'],
        'FleetOriType'          => (
            $fleetEntry['fleet_start_type'] == 1 ?
            'planet' :
            (
                $fleetEntry['fleet_start_type'] == 3 ?
                'moon' :
                'debris'
            )
        ),
        'FleetOriStart'         => date('d.m.Y<\b\r/>H:i:s', $fleetEntry['fleet_send_time']),
        // Destination details
        'FleetDesGalaxy'        => $fleetEntry['fleet_end_galaxy'],
        'FleetDesSystem'        => $fleetEntry['fleet_end_system'],
        'FleetDesPlanet'        => $fleetEntry['fleet_end_planet'],
        'FleetDesType'          => (
            $fleetEntry['fleet_end_type'] == 1 ?
            'planet' :
            (
                $fleetEntry['fleet_end_type'] == 3 ?
                'moon' :
                'debris'
            )
        ),
        'FleetDesArrive'        => date('d.m.Y<\b\r/>H:i:s', $fleetEntry['fleet_start_time']),
        // Times
        'FleetEndTime'          => date('d.m.Y<\b\r/>H:i:s', $fleetEntry['fleet_end_time']),
        'FleetFlyTargetTime'    => (
            $flightTimeRemaining > 0 ?
            (
                '<b class="lime flRi" id="bxxft_' .
                $fleetId .
                '">' .
                pretty_time($flightTimeRemaining, true, 'D') .
                '</b>'
            ) :
            ''
        ),
        'FleetFlyBackTime' => (
            !$isNotCalculatedStationMission ?
            (
                $flightComeBackRemaining > 0 ?
                    (
                        '<b class="lime flRi" id="bxxfb_' .
                        $fleetId .
                        '">' .
                        pretty_time($flightComeBackRemaining, true, 'D') .
                        '</b>'
                    ) :
                    (
                        '<b class="lime flRi">' .
                        $_Lang['fl_already_cameback'] .
                        '</b>'
                    )
            ) :
            ''
        ),
        'FleetFlyStayTime' => (
            ($flightStayRemaining > 0) ?
                '<b class="lime flRi" id="bxxfs_'.$fleetId.'">'.pretty_time($flightStayRemaining, true, 'D').'</b>' :
                ''
        ),
        'FleetRetreatTime' => (
            $isRetreatStillAvailable ?
            (
                (
                    $isFleetMissionNotCalculated &&
                    $flightTimeRemaining > 0
                ) ?
                    '<b class="flRi" id="bxxfr_'.$fleetId.'">'.pretty_time($flightElapsedTime, true, 'D').'</b>' :
                    '<b class="flRi">'.pretty_time($fleetEntry['fleet_start_time'] - $fleetEntry['fleet_send_time'], true, 'D').'</b>'
            ) :
            ''
        ),
        'FleetHideTargetTime'           => ($flightTimeRemaining <= 0 ? ' class="hide"' : ''),
        'FleetHideTargetorBackTime'     => ($flightTimeRemaining <= 0 ? ' class="hide"' : ''),
        'FleetHideComeBackTime'     => (
            $isNotCalculatedStationMission ?
            ' class="hide"' :
            ''
        ),
        'FleetHideStayTime'         => (
            $flightStayRemaining <= 0 ?
            ' class="hide"' :
            ''
        ),
        'FleetHideRetreatTime'      => (
            !$isRetreatStillAvailable ?
            ' class="hide"' :
            ''
        ),

        'FleetOrders'           => implode('', $availableFleetOrders),

        'data'                  => [
            'ships' => $fleetShips,
            'extraShipsInUnion' => $extraShipsInUnion,
        ],

        'addons'                => [
            'chronoApplets'     => [
                (
                    $flightTimeRemaining > 0 ?
                        InsertJavaScriptChronoApplet('ft_', $fleetId, $flightTimeRemaining) :
                        ''
                ),
                (
                    $flightStayRemaining > 0 ?
                        InsertJavaScriptChronoApplet('fs_', $fleetId, $flightStayRemaining) :
                        ''
                ),
                (
                    $flightComeBackRemaining > 0 ?
                        InsertJavaScriptChronoApplet('fb_', $fleetId, $flightComeBackRemaining) :
                        ''
                ),
                (
                    (
                        $isRetreatStillAvailable &&
                        $isFleetMissionNotCalculated &&
                        $flightTimeRemaining > 0
                    ) ?
                        InsertJavaScriptChronoApplet(
                            'fr_',
                            $fleetId,
                            $fleetEntry['fleet_send_time'],
                            true,
                            true,
                            false,
                            [ 'reverseEndTimestamp' => $fleetEntry['fleet_start_time'] ]
                        ) :
                        ''
                )
            ],
        ],
    ];

    return $listElement;
}

?>
