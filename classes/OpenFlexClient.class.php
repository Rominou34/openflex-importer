<?php

namespace OpenFlexImporter;

class OpenFlexClient {
    private $token;

    const VEHICLES_EXAMPLE = <<<EOD
{
    "total": 2,
    "items": [
        {
            "id": 199,
            "entityId": 41,
            "pointOfSale": {
                "id": 54,
                "name": "Point de vente 2 (François D.)",
                "zipCode": "69003",
                "city": "Lyon",
                "latitude": null,
                "longitude": null
            },
            "physicalPresencePointOfSale": {
                "id": 53,
                "name": "Point de vente 1 (François D.)",
                "zipCode": "71100",
                "city": "Chalon-sur-Saône",
                "latitude": null,
                "longitude": null
            },
            "physicalPresenceSite": null,
            "configuration": 3,
            "destination": 1,
            "referential": 1,
            "referentialCarId": "82825",
            "typeId": "10",
            "type": "Véhicule particulier",
            "makeId": "16",
            "make": "JAGUAR",
            "modelGroupId": "2365",
            "modelGroup": "X-TYPE",
            "generation": "X-TYPE",
            "modelId": "2365",
            "model": "X-TYPE",
            "trim": "Classique",
            "version": "X-Type 2.0i V6",
            "fuelId": "00100001",
            "fuel": "Essence sans plomb",
            "genericFuelId": 1,
            "genericFuel": "Essence",
            "transmissionTypeId": "00180001",
            "transmissionType": "Boîte manuelle",
            "genericTransmissionTypeId": 1,
            "genericTransmissionType": "Boîte manuelle",
            "bodyId": "00010060",
            "body": "Berline",
            "genericBodyId": 11,
            "genericBody": "Berline",
            "segmentationId": "00030005",
            "segmentation": "Moyenne supérieure",
            "driveTypeId": "00050001",
            "driveType": "Traction avant",
            "genericDriveTypeId": 1,
            "genericDriveType": "2 roues motrices",
            "emissionId": "00170007",
            "emission": "G-Kat",
            "seats": 5,
            "gears": 5,
            "doors": 4,
            "valves": 4,
            "torque": 196,
            "displacement": 2099,
            "cylinders": 6,
            "cylindersTypeId": "00080002",
            "cylindersType": "V",
            "horsepower": 159,
            "taxHorsepower": 10,
            "kilowatt": 117,
            "co2Emission": 219,
            "wltpCo2EmissionMin": null,
            "wltpCo2EmissionMax": null,
            "realCo2Emission": 219,
            "mixedConsumption": 9.2,
            "urbanConsumption": 12.7,
            "extraUrbanConsumption": 7.1,
            "length": 4672,
            "width": 2003,
            "height": 1392,
            "totalWeight": 2025,
            "payload": 575,
            "wheelbase": null,
            "trailerLoadBraked": null,
            "trailerLoadUnbraked": null,
            "roofload": 75,
            "energeticEfficiency": null,
            "curbWeight": 1450,
            "realCurbWeight": null,
            "topSpeed": 210,
            "acceleration": 9.4,
            "trunkMinimumCapacity": 452,
            "trunkMaximumCapacity": 0,
            "modelCatalogBegin": "2002-02-01T01:00:00+01:00",
            "modelCatalogEnd": "2005-08-01T02:00:00+02:00",
            "catalogBeginPrice": "2002-02-01T01:00:00+01:00",
            "catalogEndPrice": "2003-08-31T02:00:00+02:00",
            "catalogExclVatPrice": 28595.32,
            "catalogInclVatPrice": 34200,
            "catalogTax": 19.6,
            "catalogPriceOverloaded": false,
            "frontTyreWidth": "205",
            "frontTyreHeight": "55",
            "frontTyreSpeedIndex": "R",
            "frontTyreDiameter": "16",
            "rearTyreWidth": "205",
            "rearTyreHeight": "55",
            "rearTyreSpeedIndex": "R",
            "rearTyreDiameter": "16",
            "internalColorCode": null,
            "internalColorWording": "Noir",
            "internalColorReference": "000000",
            "externalColorCode": null,
            "externalColorWording": "Bleu",
            "externalColorReference": "007cad",
            "putIntoService": "2002-03-19T01:00:00+01:00",
            "mileage": 128597,
            "mileageDate": "2021-06-05T01:04:33+02:00",
            "guaranteedMileage": true,
            "numberplate": "DC789AT",
            "chassis": "SAJAA53S22YC32220",
            "registration": "2019-12-04T01:00:00+01:00",
            "registrationNumber": null,
            "codeFactory": null,
            "recoverableVat": 2,
            "tcenum": "MDA17x2Hxxxx",
            "cultureId": null,
            "referentialPicture": "https://filerender-api.openflex-preprod.eu/files/images/CvgF3D6OQwoCk%2Btflg2aX3Zf4mzC1n7YqV2Pteut6zd6nWb_DFXWZWcSeoPfUCXXnczP06IA8eLnHuFddcEad89rgETGkwGiog%3D%3D",
            "picture": "https://filerender-api.openflex-preprod.eu/files/images/c3dGREFEbW93VjRWS2dVaL63nYj2qV75%2BDAqoBodQixUo7fs64m75Kg5J%2BDSgJUIPs98gD2JbQHK96dG9SmHiDXFF_BuwkxMDF6VjVncOOa3jvRZ/JAGUAR_X-TYPE_34_AVANT_GAUCHE.jpg",
            "damaged": false,
            "firstHand": false,
            "rolling": true,
            "upToDateMaintenanceBook": true,
            "purchaseSource": "Reprise sur VN",
            "origin": 1,
            "import": false,
            "importCountryIso": null,
            "depositSale": false,
            "previousOwner": "TOTO",
            "sellerBuyer": "François DE MONSABERT",
            "forecastedMileage": null,
            "forecastedDate": null,
            "referenced": true,
            "stockEntrance": "2021-06-05T01:04:39+02:00",
            "internalNumber": "VOJAG6545",
            "standingTimeAlert": false,
            "automaticPriceUpdate": false,
            "isExported": false,
            "technicalControl": "2021-06-04T01:05:49+02:00",
            "nextTechnicalControl": null,
            "status": {
                "code": "SAST",
                "wording": "Stock de vente"
            },
            "deliveryDate": null,
            "ordered": null,
            "warranty": "VO24",
            "warrantyDuration": null,
            "warrantyEnd": "2021-10-29T18:52:09+02:00",
            "assignment": null,
            "collaboratorAssignmentId": null,
            "reconciliationStatus": null,
            "price": 9500,
            "referentialPollutionClassification": null,
            "pollutionClassificationDesignation": null,
            "pollutionClassificationValue": null,
            "pollutionClassificationMedia": null,
            "comment": "commentaire test VO",
            "eReserved": false,
            "updatedAt": "2023-05-04T17:01:17+02:00",
            "powertrainId": null,
            "powertrain": null,
            "numberOfBatteries": null,
            "batteryTypeId": null,
            "batteryType": null,
            "batteryCapacityAh": null,
            "batteryCapacityKwh": null,
            "homeBatteryChargingTime": null,
            "fastBatteryChargingTime": null,
            "hybridizationTypeId": null,
            "hybridizationType": null,
            "autonomy": null,
            "gasTypeId": null,
            "gasType": null,
            "electricConsumption": null,
            "gasTankCapacity": null,
            "gasTankCapacityUnit": null,
            "gasUrbanConsumption": null,
            "gasExtraUrbanConsumption": null,
            "gasMixedConsumption": null,
            "gasConsumptionUnit": null,
            "federalMotorTransportAuthorityNumber": null,
            "previousOwnerCustomer": null,
            "insurancePremiumIndex": null,
            "trailerHitch": null,
            "trailerHitchValue": null,
            "bonusPrice": null,
            "penaltyPrice": null,
            "privateExclVatPrice": 9500,
            "privateInclVatPrice": 9500,
            "professionalExclVatPrice": null,
            "professionalInclVatPrice": null,
            "equipmentsPrice": 2280,
            "saddleriesPrice": 0,
            "paintingsPrice": 0,
            "serviceAgreementsPrice": 960,
            "damagesRepaired": null
        },
        {
            "id": 45,
            "entityId": 41,
            "pointOfSale": {
                "id": 53,
                "name": "Point de vente 1 (François D.)",
                "zipCode": "71100",
                "city": "Chalon-sur-Saône",
                "latitude": null,
                "longitude": null
            },
            "physicalPresencePointOfSale": {
                "id": 53,
                "name": "Point de vente 1 (François D.)",
                "zipCode": "71100",
                "city": "Chalon-sur-Saône",
                "latitude": null,
                "longitude": null
            },
            "physicalPresenceSite": null,
            "configuration": 3,
            "destination": 2,
            "referential": 1,
            "referentialCarId": "204897",
            "typeId": "20",
            "type": "Véhicule tout terrain",
            "makeId": "21",
            "make": "MERCEDES",
            "modelGroupId": "5680",
            "modelGroup": "CLASSE GLA",
            "generation": null,
            "modelId": "6553",
            "model": "GLA",
            "trim": "Inspiration",
            "version": "GLA 200",
            "fuelId": "00100001",
            "fuel": "Essence sans plomb",
            "genericFuelId": 1,
            "genericFuel": "Essence",
            "transmissionTypeId": "00180001",
            "transmissionType": "Boîte manuelle",
            "genericTransmissionTypeId": 1,
            "genericTransmissionType": "Boîte manuelle",
            "bodyId": "00010095",
            "body": "Tout-Terrain",
            "genericBodyId": 2,
            "genericBody": "SUV",
            "segmentationId": "00030050",
            "segmentation": "SUV légers",
            "driveTypeId": "00050001",
            "driveType": "Traction avant",
            "genericDriveTypeId": 1,
            "genericDriveType": "2 roues motrices",
            "emissionId": "00170007",
            "emission": "G-Kat",
            "seats": 5,
            "gears": 6,
            "doors": 5,
            "valves": 4,
            "torque": 250,
            "displacement": 1595,
            "cylinders": 4,
            "cylindersTypeId": "00080001",
            "cylindersType": "Ligne",
            "horsepower": 156,
            "taxHorsepower": 9,
            "kilowatt": 115,
            "co2Emission": 141,
            "wltpCo2EmissionMin": null,
            "wltpCo2EmissionMax": null,
            "realCo2Emission": 141,
            "mixedConsumption": 6.6,
            "urbanConsumption": 0,
            "extraUrbanConsumption": 0,
            "length": 4424,
            "width": 1804,
            "height": 1494,
            "totalWeight": 1940,
            "payload": 545,
            "wheelbase": null,
            "trailerLoadBraked": null,
            "trailerLoadUnbraked": null,
            "roofload": 0,
            "energeticEfficiency": null,
            "curbWeight": 1395,
            "realCurbWeight": null,
            "topSpeed": 215,
            "acceleration": 8.4,
            "trunkMinimumCapacity": 421,
            "trunkMaximumCapacity": 0,
            "modelCatalogBegin": "2018-05-01T02:00:00+02:00",
            "modelCatalogEnd": "2020-03-01T01:00:00+01:00",
            "catalogBeginPrice": "2019-09-03T02:00:00+02:00",
            "catalogEndPrice": "2020-03-31T02:00:00+02:00",
            "catalogExclVatPrice": 29416.67,
            "catalogInclVatPrice": 35300,
            "catalogTax": 20,
            "catalogPriceOverloaded": false,
            "frontTyreWidth": "215",
            "frontTyreHeight": "60",
            "frontTyreSpeedIndex": "R",
            "frontTyreDiameter": "17",
            "rearTyreWidth": "215",
            "rearTyreHeight": "60",
            "rearTyreSpeedIndex": "R",
            "rearTyreDiameter": "17",
            "internalColorCode": null,
            "internalColorWording": "Noir",
            "internalColorReference": "000000",
            "externalColorCode": null,
            "externalColorWording": "Blanc",
            "externalColorReference": "ffffff",
            "putIntoService": "2021-09-23T02:00:00+02:00",
            "mileage": 19527,
            "mileageDate": null,
            "guaranteedMileage": false,
            "numberplate": "FK589NV",
            "chassis": "WDC1569431J661747",
            "registration": null,
            "registrationNumber": null,
            "codeFactory": "156943_FR1",
            "recoverableVat": 2,
            "tcenum": "n.c.",
            "cultureId": null,
            "referentialPicture": "https://filerender-api.openflex-preprod.eu/files/images/plsQ_2MLjse40wQUWvUZRBz3_W4HkPNYga+GmO7DlbuIOR0gr+fgic2wAQISUy6KNKp0EYR_MjJnCfMBmq9rgTWbZecI_fYSzg==",
            "picture": "https://filerender-api.openflex-preprod.eu/files/images/c3dGREFEbW93VjRWS2dVaL63nYj2qV75%2BDAqoBodQixUo7fs64m75Kg5J%2BDSgJUIPs98gD2JbQHK96dG9SmHiDXFF_BuwkxODF6UjVfYJLXpgw%3D%3D/MERCEDES_GLA_FACE_AVANT.jpg",
            "damaged": false,
            "firstHand": true,
            "rolling": true,
            "upToDateMaintenanceBook": false,
            "purchaseSource": null,
            "origin": 1,
            "import": false,
            "importCountryIso": null,
            "depositSale": false,
            "previousOwner": null,
            "sellerBuyer": null,
            "forecastedMileage": 19527,
            "forecastedDate": null,
            "referenced": true,
            "stockEntrance": "2021-09-01T00:00:00+02:00",
            "internalNumber": "VOMER123",
            "standingTimeAlert": false,
            "automaticPriceUpdate": false,
            "isExported": false,
            "technicalControl": null,
            "nextTechnicalControl": null,
            "status": {
                "code": "SAST",
                "wording": "Stock de vente"
            },
            "deliveryDate": null,
            "ordered": null,
            "warranty": "VO24",
            "warrantyDuration": 24,
            "warrantyEnd": null,
            "assignment": null,
            "collaboratorAssignmentId": null,
            "reconciliationStatus": null,
            "price": 26987.2,
            "referentialPollutionClassification": null,
            "pollutionClassificationDesignation": null,
            "pollutionClassificationValue": null,
            "pollutionClassificationMedia": null,
            "comment": null,
            "eReserved": false,
            "updatedAt": "2023-05-04T17:01:17+02:00",
            "powertrainId": null,
            "powertrain": null,
            "numberOfBatteries": null,
            "batteryTypeId": null,
            "batteryType": null,
            "batteryCapacityAh": null,
            "batteryCapacityKwh": null,
            "homeBatteryChargingTime": null,
            "fastBatteryChargingTime": null,
            "hybridizationTypeId": null,
            "hybridizationType": null,
            "autonomy": null,
            "gasTypeId": null,
            "gasType": null,
            "electricConsumption": null,
            "gasTankCapacity": null,
            "gasTankCapacityUnit": null,
            "gasUrbanConsumption": null,
            "gasExtraUrbanConsumption": null,
            "gasMixedConsumption": null,
            "gasConsumptionUnit": null,
            "federalMotorTransportAuthorityNumber": null,
            "previousOwnerCustomer": null,
            "insurancePremiumIndex": null,
            "trailerHitch": null,
            "trailerHitchValue": null,
            "bonusPrice": null,
            "penaltyPrice": null,
            "privateExclVatPrice": 27790.7,
            "privateInclVatPrice": 27790.7,
            "professionalExclVatPrice": 26987.2,
            "professionalInclVatPrice": 26987.2,
            "equipmentsPrice": 850,
            "saddleriesPrice": 0,
            "paintingsPrice": 0,
            "serviceAgreementsPrice": 0,
            "damagesRepaired": false
        }
    ]
}
EOD;

    // @TODO - Appel API pour récupérer le token
    function __construct() {
    }

    // Récupération de la liste des véhicules
    function getVehicles($max_lines = 0, $offset = 0, $params = []) {
        return json_decode(self::VEHICLES_EXAMPLE, true)['items'];
    }

    // @TODO - Faire appel API pour renvoyer nombre d'objets
    function countRows() {
        return 2;
    }

    // Fonctions pour les appels
    function sendRequest($url, $type = "POST") {

    }

}
?>