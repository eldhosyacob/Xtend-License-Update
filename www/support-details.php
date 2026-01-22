<?php
declare(strict_types=1);
require_once('config/auth_check.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Details - Xtend License Update</title>
    <link rel="stylesheet" href="styles/users.css">
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="styles/licenses.css">
    <style>
        .details-container {
            display: none;
        }

        .details-container.visible {
            display: block;
        }

        /* Override licenses.css specific styles if needed */
        .btn-update {
            background-color: #2563eb;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-update:hover {
            background-color: #1d4ed8;
        }

        /* Ensure disabled inputs look clearly disabled */
        input:disabled,
        select:disabled {
            background-color: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        /* Toast Notification Styles */
        .toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 16px;
            position: fixed;
            z-index: 3000;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transition: opacity 0.3s, bottom 0.3s;
        }

        .toast.show {
            visibility: visible;
            opacity: 1;
            bottom: 50px;
        }

        .toast.success {
            background-color: #10b981;
            /* Green */
        }

        .toast.error {
            background-color: #ef4444;
            /* Red */
        }

        .toast-icon {
            font-size: 1.2em;
        }
    </style>
</head>

<body>

    <?php include 'components/header-sidebar.php'; ?>

    <div class="page-containers">
        <div class="heading">
            SUPPORT DETAILS
        </div>

        <div class="search-container" style="margin-bottom: 24px; display:flex; justify-content:center;">
            <div
                style="background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); border:1px solid #e2e8f0; display:flex; gap:32px; flex-wrap:wrap; align-items:flex-end;">

                <!-- Serial Search -->
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label for="searchSerialId" style="font-weight:600; font-size:14px; color:#374151;">Device Serial
                        ID</label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="text" id="searchSerialId" class="form-control" placeholder="Enter Serial ID..."
                            autocomplete="off"
                            style="width:220px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; transition: all 0.2s;">
                        <button type="button" onclick="performSearch('serial_id')" class="btn btn-primary"
                            style="padding: 10px 14px; border-radius: 6px; border: none; background: #3b82f6; color: white; cursor: pointer; transition: background 0.2s;">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Vertical Divider (optional, using border) -->
                <div
                    style="width:1px; background:#e2e8f0; height:50px; display:none; @media(min-width: 600px){display:block;}">
                </div>

                <!-- Location Search -->
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label for="searchLocationCode" style="font-weight:600; font-size:14px; color:#374151;">Location
                        Code</label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="text" id="searchLocationCode" class="form-control"
                            placeholder="Enter Location Code..." autocomplete="off"
                            style="width:220px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; transition: all 0.2s;">
                        <button type="button" onclick="performSearch('location_code')" class="btn btn-primary"
                            style="padding: 10px 14px; border-radius: 6px; border: none; background: #3b82f6; color: white; cursor: pointer; transition: background 0.2s;">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <div id="noResultsMessage"
            style="display:none; justify-content:center; align-items:center; flex-direction:column; padding:40px; text-align:center; background:#fff; border-radius:12px; margin-bottom: 24px;margin-left:24px;margin-right:24px">
            <div style="font-size:48px; color:#94a3b8; margin-bottom:16px;">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h3 style="margin:0; font-size:18px; color:#475569; font-weight:600;" id="noResultsMsgText">No results found
            </h3>
            <!-- <p style="margin:8px 0 0 0; color:#64748b; font-size:14px;">We couldn't find any license details matching
                your search.</p> -->
        </div>

        <div id="detailsArea" class="details-container">
            <form id="supportForm"
                style="display:block; margin:0px 0px 24px 24px; border:1px solid #e5e7eb; border-radius:12px; padding:24px; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <input type="hidden" id="licenseId" name="id">

                <!-- General Section -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        General</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">CreatedOn</label>
                            <input type="text" name="CreatedOn" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">ClientName</label>
                            <input type="text" name="ClientName" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">LocationName</label>
                            <input type="text" name="LocationName" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">LocationCode</label>
                            <input type="text" name="LocationCode" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">BoardType</label>
                            <select name="BoardType" disabled class="form-input">
                                <option value="Lichee Pi">Lichee Pi</option>
                                <option value="Tibbo">Tibbo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Licensee Section -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        Licensee</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Name]</label>
                            <input type="text" name="Licensee[Name]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Distributor]</label>
                            <input type="text" name="Licensee[Distributor]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Dealer]</label>
                            <input type="text" name="Licensee[Dealer]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Type]</label>
                            <select name="Licensee[Type]" disabled class="form-input">
                                <option value="Rental">Rental</option>
                                <option value="Purchase">Purchase</option>
                                <option value="Permanent">Permanent</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[AMCTill]</label>
                            <input type="text" name="Licensee[AMCTill]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[ValidTill]</label>
                            <input type="text" name="Licensee[ValidTill]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[BillNo]</label>
                            <input type="text" name="Licensee[BillNo]" disabled class="form-input">
                        </div>
                    </div>
                </div>

                <!-- System Section -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        System</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Type]</label>
                            <select name="System[Type]" disabled class="form-input">
                                <option value="Desktop">Desktop</option>
                                <option value="Standalone">Standalone</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[OS]</label>
                            <select name="System[OS]" disabled class="form-input">
                                <option value="Windows">Windows</option>
                                <option value="Linux">Linux</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IsVM]</label>
                            <select name="System[IsVM]" disabled class="form-input">
                                <option value="false">false</option>
                                <option value="true">true</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[SerialID]</label>
                            <input type="text" name="System[SerialID]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[UniqueID]</label>
                            <input type="text" name="System[UniqueID]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[BuildType]</label>
                            <input type="text" name="System[BuildType]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Debug]</label>
                            <select name="System[Debug]" disabled class="form-input">
                                <option value="0">0</option>
                                <option value="1">1</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][Type]</label>
                            <select name="System[IPSettings][Type]" disabled class="form-input">
                                <option value="Static">Static</option>
                                <option value="DHCP">DHCP</option>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][IP]</label>
                            <input type="text" name="System[IPSettings][IP]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][Gateway]</label>
                            <input type="text" name="System[IPSettings][Gateway]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][Dns]</label>
                            <input type="text" name="System[IPSettings][Dns]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Passwords][System]</label>
                            <input type="text" name="System[Passwords][System]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Passwords][Web]</label>
                            <input type="text" name="System[Passwords][Web]" disabled class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Engine Section -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        Engine</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[Build]</label>
                            <input type="text" name="Engine[Build]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[GracePeriod]</label>
                            <input type="number" name="Engine[GracePeriod]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[MaxPorts]</label>
                            <input type="text" name="Engine[MaxPorts]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidStartTZ]</label>
                            <input type="number" name="Engine[ValidStartTZ]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidEndTZ]</label>
                            <input type="number" name="Engine[ValidEndTZ]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidCountries]</label>
                            <input type="number" name="Engine[ValidCountries]" disabled class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Hardware/Analog Section -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        Hardware</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device
                                ID 1</label>
                            <input type="number" name="device_id1" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">PortsEnabled
                                1</label>
                            <input type="text" name="ports_enabled_deviceid1" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device
                                ID 2</label>
                            <input type="number" name="device_id2" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">PortsEnabled
                                2</label>
                            <input type="text" name="ports_enabled_deviceid2" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device
                                ID 3</label>
                            <input type="number" name="device_id3" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">PortsEnabled
                                3</label>
                            <input type="text" name="ports_enabled_deviceid3" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device
                                ID 4</label>
                            <input type="number" name="device_id4" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">PortsEnabled
                                4</label>
                            <input type="text" name="ports_enabled_deviceid4" disabled class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Centralization Section -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        Centralization</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[LiveStatusUrl]</label>
                            <input type="text" name="Centralization[LiveStatusUrl]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[LiveStatusUrlInterval]</label>
                            <input type="number" name="Centralization[LiveStatusUrlInterval]" disabled
                                class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[UploadFileUrl]</label>
                            <input type="text" name="Centralization[UploadFileUrl]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[UploadFileUrlInterval]</label>
                            <input type="number" name="Centralization[UploadFileUrlInterval]" disabled
                                class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[SettingsUrl]</label>
                            <input type="text" name="Centralization[SettingsUrl]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[UserTrunkMappingUrl]</label>
                            <input type="text" name="Centralization[UserTrunkMappingUrl]" disabled class="form-input">
                        </div>
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[PhoneBookUrl]</label>
                            <input type="text" name="Centralization[PhoneBookUrl]" disabled class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Features -->
                <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                    <h3
                        style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                        Features</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                        <div>
                            <label
                                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Features[Script]</label>
                            <input type="text" name="Features[Script]" disabled class="form-input"
                                style="width:25% !important;">
                        </div>
                    </div>
                </div>

                <!-- Device Status (Editable) -->
                <div style="margin-bottom:16px; display:flex; gap:16px; align-items:flex-end;">
                    <div>
                        <label
                            style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">DeviceStatus</label>
                        <select name="DeviceStatus" id="DeviceStatusSelect" class="form-input" style="min-width:180px;">
                            <option value="">Select Status</option>
                            <option value="Testing">Testing</option>
                            <option value="Ready For Dispatch">Ready For Dispatch</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Installed">Installed</option>
                            <option value="Serviced">Serviced</option>
                            <option value="Replaced">Replaced</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">StatusDate</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input type="date" name="StatusDate" id="StatusDateInput" class="form-input"
                                style="width:140px;">
                            <div style="cursor:pointer; color:#64748b; display:flex; align-items:center;"
                                onclick="showHistory('status')" title="View Device Status History">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comment (Editable) -->
                <div style="margin-bottom:24px;">
                    <label
                        style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Comment</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="text" name="Comment" id="CommentInput" class="form-input"
                            style="width:25%; max-width:500px;">
                        <div style="cursor:pointer; color:#64748b; display:flex; align-items:center;"
                            onclick="showHistory('comment')" title="View Previous Comments">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- TestedBy (Editable) -->
                <div style="margin-bottom:24px;">
                    <label
                        style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">TestedBy</label>
                    <input type="text" name="TestedBy" id="TestedByInput" class="form-input"
                        style="width:25%; max-width:500px;">
                </div>

                <!-- Support Remarks (Editable) - New Field -->
                <div style="margin-bottom:24px;">
                    <label
                        style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Support
                        Remarks</label>
                    <div style="display:flex; align-items:flex-start; gap:10px;">
                        <textarea name="SupportRemarks" id="SupportRemarksInput" class="form-input" rows="6"
                            style="width:50%; max-width:600px; resize:vertical;"></textarea>
                        <div style="cursor:pointer; color:#64748b; display:flex; align-items:center; margin-top:8px;"
                            onclick="showHistory('remark')" title="View Support Remarks History">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <div
                    style="margin-top:24px; padding-top:20px; border-top:2px solid #f1f5f9; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
                    <button type="submit" id="btnSave" class="btn-update">Save Data</button>
                    <!-- <div style="font-size:12px; color:#64748b; align-self:center;">(Saves only Device Status, Status Date, Tested By, Comment, Support Remarks to DB)</div> -->
                </div>

            </form>
        </div>
    </div>

    <!-- Generic History Popup -->
    <div id="historyPopup"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div class="popup-content"
            style="background:white; padding:24px; border-radius:12px; max-width:800px; max-height: 60vh; width:90%; box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); display: flex; flex-direction: column;">
            <h3 id="historyPopupTitle"
                style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; flex-shrink: 0;">History</h3>
            <div id="historyPopupBody" style="margin-bottom:24px; overflow-y:auto; flex: 1; min-height: 0;"></div>
            <div style="text-align:right; flex-shrink: 0;">
                <button type="button" onclick="closePopup()"
                    style="padding:8px 16px; background:#f16767; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Close</button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.7); z-index:2000; justify-content:center; align-items:center;">
        <div class="spinner"
            style="border: 4px solid #f3f3f3; border-top: 4px solid #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;">
        </div>
    </div>
    <style>
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <i class="fa-solid fa-circle-check toast-icon"></i>
        <span class="toast-message">Operation successful</span>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Global functions
        function showLoading(show) {
            $('#loadingOverlay').css('display', show ? 'flex' : 'none');
        }

        function showToast(msg, type) {
            const toast = $('#toast');
            toast.find('.toast-message').text(msg);
            // Remove any existing show/type classes first to be safe
            toast.removeClass('show success error');
            // Force reflow
            void toast[0].offsetWidth;
            toast.addClass('toast ' + type + ' show');

            // Clear any existing timeout
            if (toast.data('timeout')) {
                clearTimeout(toast.data('timeout'));
            }

            const timeout = setTimeout(() => toast.removeClass('show'), 3000);
            toast.data('timeout', timeout);
        }

        $(document).ready(function () {
            // Enter Key Bindings for Search
            $('#searchSerialId').on('keypress', function (e) {
                if (e.which == 13) {
                    e.preventDefault();
                    performSearch('serial_id');
                }
            });
            $('#searchLocationCode').on('keypress', function (e) {
                if (e.which == 13) {
                    e.preventDefault();
                    performSearch('location_code');
                }
            });

            // Save
            $('#supportForm').on('submit', function (e) {
                e.preventDefault();
                showLoading(true);

                const payload = {
                    id: $('#licenseId').val(),
                    DeviceStatus: $('#DeviceStatusSelect').val(),
                    StatusDate: $('#StatusDateInput').val(),
                    TestedBy: $('#TestedByInput').val(),
                    Comment: $('#CommentInput').val(),
                    SupportRemarks: $('#SupportRemarksInput').val()
                };

                $.ajax({
                    url: 'api/support_details.php',
                    method: 'POST',
                    data: payload,
                    dataType: 'json',
                    success: function (response) {
                        showLoading(false);
                        if (response.success) {
                            showToast('Data saved successfully', 'success');
                        } else {
                            showToast(response.message || 'Save failed', 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        showLoading(false);
                        console.error("Save error:", error);
                        showToast('Error occurred during save', 'error');
                    }
                });
            });
        });

        // Global functions (accessible via onclick)

        function performSearch(type) {
            let searchTerm = '';
            if (type === 'serial_id') {
                searchTerm = $('#searchSerialId').val().trim();
            } else if (type === 'location_code') {
                searchTerm = $('#searchLocationCode').val().trim();
            } else {
                return;
            }

            if (!searchTerm) {
                showToast('Please enter a value to search', 'error');
                return;
            }

            // Clear the other input
            if (type === 'serial_id') {
                $('#searchLocationCode').val('');
            } else {
                $('#searchSerialId').val('');
            }

            // Hide details area initially
            $('#detailsArea').removeClass('visible');
            $('#noResultsMessage').hide(); // Hide previous no results message

            showLoading(true);
            $.ajax({
                url: 'api/support_details.php',
                method: 'GET',
                data: { search_term: searchTerm, search_type: type },
                dataType: 'json',
                success: function (response) {
                    showLoading(false);
                    if (response.success && response.data) {
                        populateForm(response.data);
                    } else {
                        // Ensure details area is hidden
                        $('#detailsArea').removeClass('visible');
                        // Show centered "No results found" message
                        $('#noResultsMsgText').text(response.message || 'No results found');
                        $('#noResultsMessage').fadeIn().css('display', 'flex');
                    }
                },
                error: function (xhr) {
                    showLoading(false);
                    // showToast('Error fetching details', 'error');
                    $('#detailsArea').removeClass('visible');

                    // Show error in the centered message box as well
                    $('#noResultsMsgText').text('Error fetching details');
                    $('#noResultsMessage').fadeIn().css('display', 'flex');
                }
            });
        }

        function populateForm(data) {
            $('#licenseId').val(data.id);

            // Map flat API data to Form Fields
            $('input[name="CreatedOn"]').val(data.created_on);
            $('input[name="ClientName"]').val(data.client_name);
            $('input[name="LocationName"]').val(data.location_name);
            $('input[name="LocationCode"]').val(data.location_code);
            $('select[name="BoardType"]').val(data.board_type);

            $('input[name="Licensee[Name]"]').val(data.licensee_name);
            $('input[name="Licensee[Distributor]"]').val(data.licensee_distributor);
            $('input[name="Licensee[Dealer]"]').val(data.licensee_dealer);
            $('select[name="Licensee[Type]"]').val(data.licensee_type);
            $('input[name="Licensee[AMCTill]"]').val(data.licensee_amctill);
            $('input[name="Licensee[ValidTill]"]').val(data.licensee_validtill);
            $('input[name="Licensee[BillNo]"]').val(data.licensee_billno);

            $('select[name="System[Type]"]').val(data.system_type);
            $('select[name="System[OS]"]').val(data.system_os);
            $('select[name="System[IsVM]"]').val(data.system_isvm);
            $('input[name="System[SerialID]"]').val(data.system_serialid);
            $('input[name="System[UniqueID]"]').val(data.system_uniqueid);
            $('input[name="System[BuildType]"]').val(data.system_build_type);
            $('select[name="System[Debug]"]').val(data.system_debug);

            $('select[name="System[IPSettings][Type]"]').val(data.system_ipsettings_type);
            $('input[name="System[IPSettings][IP]"]').val(data.system_ipsettings_ip);
            $('input[name="System[IPSettings][Gateway]"]').val(data.system_ipsettings_gateway);
            $('input[name="System[IPSettings][Dns]"]').val(data.system_ipsettings_dns);

            $('input[name="System[Passwords][System]"]').val(data.system_passwords_system);
            $('input[name="System[Passwords][Web]"]').val(data.system_passwords_web);

            $('input[name="Engine[Build]"]').val(data.engine_build);
            $('input[name="Engine[GracePeriod]"]').val(data.engine_graceperiod);
            $('input[name="Engine[MaxPorts]"]').val(data.engine_maxports);
            $('input[name="Engine[ValidStartTZ]"]').val(data.engine_validstarttz);
            $('input[name="Engine[ValidEndTZ]"]').val(data.engine_validendtz);
            $('input[name="Engine[ValidCountries]"]').val(data.engine_validcountries);

            $('input[name="device_id1"]').val(data.device_id1);
            $('input[name="ports_enabled_deviceid1"]').val(data.ports_enabled_deviceid1);
            $('input[name="device_id2"]').val(data.device_id2);
            $('input[name="ports_enabled_deviceid2"]').val(data.ports_enabled_deviceid2);
            $('input[name="device_id3"]').val(data.device_id3);
            $('input[name="ports_enabled_deviceid3"]').val(data.ports_enabled_deviceid3);
            $('input[name="device_id4"]').val(data.device_id4);
            $('input[name="ports_enabled_deviceid4"]').val(data.ports_enabled_deviceid4);

            $('input[name="Centralization[LiveStatusUrl]"]').val(data.centralization_livestatusurl);
            $('input[name="Centralization[LiveStatusUrlInterval]"]').val(data.centralization_livestatusurlinterval);
            $('input[name="Centralization[UploadFileUrl]"]').val(data.centralization_uploadfileurl);
            $('input[name="Centralization[UploadFileUrlInterval]"]').val(data.centralization_uploadfileurlinterval);
            $('input[name="Centralization[SettingsUrl]"]').val(data.centralization_settingsurl);
            $('input[name="Centralization[UserTrunkMappingUrl]"]').val(data.centralization_usertrunkmappingurl);
            $('input[name="Centralization[PhoneBookUrl]"]').val(data.centralization_phonebookurl);

            $('input[name="Features[Script]"]').val(data.features_script);

            // Editable / Special Fields
            $('#DeviceStatusSelect').val(data.device_status || '');

            // Handle Date Input format (YYYY-MM-DD)
            // Per user request: Empty by default
            $('#StatusDateInput').val('');

            $('#TestedByInput').val(data.tested_by);
            // Per user request: Empty by default
            $('#CommentInput').val('');

            // Support Remarks
            // Per user request: Empty by default
            $('#SupportRemarksInput').val('');

            $('#detailsArea').addClass('visible');
        }

        function closePopup() {
            $('#historyPopup').fadeOut();
        }

        function showHistory(type) {
            const licenseId = $('#licenseId').val();
            if (!licenseId) return;

            let apiUrl = '';
            let title = '';

            if (type === 'status') {
                apiUrl = 'api/device_status.php';
                title = 'Device Status History';
            } else if (type === 'comment') {
                apiUrl = 'api/comments.php';
                title = 'Comment History';
            } else if (type === 'remark') {
                apiUrl = 'api/support_remarks.php';
                title = 'Support Remarks History';
            }

            $('#historyPopupTitle').text(title);
            $('#historyPopupBody').html('<p style="text-align:center;">Loading...</p>');
            $('#historyPopup').fadeIn().css('display', 'flex').css('justify-content', 'center').css('align-items', 'center');

            $.ajax({
                url: apiUrl,
                data: { license_id: licenseId },
                success: function (data) {
                    // Check if string response (error) or JSON
                    if (typeof data === 'string') {
                        try { data = JSON.parse(data); } catch (e) { }
                    }

                    renderHistory(data, type);
                },
                error: function () {
                    $('#historyPopupBody').html('<p style="color:red; text-align:center;">Failed to load history.</p>');
                }
            });
        }

        function renderHistory(data, type) {
            const container = $('#historyPopupBody');
            container.empty();

            if (!data || data.length === 0) {
                container.html('<p style="text-align:center; color:#64748b;">No history found.</p>');
                return;
            }

            let html = `<table style="width:100%; border-collapse:collapse; font-size:14px; text-align:center;">
                        <thead><tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                          <th style="padding:10px; text-align:center;">Sl No</th>
                          <th style="padding:10px; text-align:center;">User</th>
                          <th style="padding:10px; text-align:center;">Date</th>
                          <th style="padding:10px; text-align:center;">Valut</th>
                        </tr></thead><tbody>`;

            // Adjust headers based on type if needed
            // For simplicity, using generic headers, but let's be specific
            if (type === 'status') {
                html = html.replace('Valut', 'Status');
            } else if (type === 'comment') {
                html = html.replace('Valut', 'Comment').replace('User', 'Commented By');
            } else if (type === 'remark') {
                html = html.replace('Valut', 'Remark').replace('User', 'Created By');
            }

            data.forEach((row, i) => {
                let user = row.user || row.commented_by || row.created_by || 'Unknown';
                let date = row.formatted_date || row.date || row.created_at || '';
                let val = row.status || row.comment || row.remark || '';

                html += `<tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#64748b; text-align:center;">${i + 1}</td>
                    <td style="padding:10px; font-weight:500; text-align:center;">${user}</td>
                    <td style="padding:10px; color:#64748b; font-size:12px; text-align:center;">${date}</td>
                    <td style="padding:10px; color:#334155; text-align:center;">${val}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            container.html(html);
        }
    </script>
</body>

</html>