-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 29, 2026 at 01:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `s&p`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit`
--

CREATE TABLE `admin_audit` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_audit`
--

INSERT INTO `admin_audit` (`id`, `user_id`, `email`, `action`, `details`, `ip`, `created_at`) VALUES
(1, 7, 'admin@example.com', 'album_create', 'Created album: my testion', '127.0.0.1', '2026-05-22 11:22:43'),
(2, 7, 'admin@example.com', 'folder_create', 'Main folder: my testion', '127.0.0.1', '2026-05-22 11:23:24'),
(3, 7, 'admin@example.com', 'subfolder_create', 'Subfolder: dgfsdfg', '127.0.0.1', '2026-05-22 11:23:37'),
(4, 7, 'admin@example.com', 'gallery_video_upload', 'Uploaded gallery video: dsfsdg', '127.0.0.1', '2026-05-25 04:38:25'),
(5, 7, 'admin@example.com', 'video_upload', 'Uploaded video #6: sample 5s [cat 3]', '127.0.0.1', '2026-05-25 04:39:34'),
(6, 7, 'admin@example.com', 'video_upload', 'Uploaded video #7: sample 5s [cat 4]', '127.0.0.1', '2026-05-25 04:40:37'),
(7, 7, 'admin@example.com', 'folder_create', 'Main folder: ashfg fgwe iyugwte rwekit wet ow', '127.0.0.1', '2026-05-25 04:42:15'),
(8, 7, 'admin@example.com', 'file_upload', 'File: pexels-kishore-illa-50611233-11472026.jpg', '127.0.0.1', '2026-05-25 04:43:02'),
(9, 7, 'admin@example.com', 'folder_create', 'Main folder: my_demo', '127.0.0.1', '2026-05-25 05:24:26'),
(10, 7, 'admin@example.com', 'file_upload', 'File: Get Unstuck - GoReDuX.pdf', '127.0.0.1', '2026-05-25 05:24:59'),
(11, 7, 'admin@example.com', 'folder_create', 'Main folder: secound', '127.0.0.1', '2026-05-25 05:25:58'),
(12, 7, 'admin@example.com', 'subfolder_create', 'Subfolder: ex', '127.0.0.1', '2026-05-25 05:26:07'),
(13, 7, 'admin@example.com', 'file_upload', 'File: Get Unstuck - GoReDuX.pdf', '127.0.0.1', '2026-05-25 05:26:45'),
(14, 7, 'admin@example.com', 'user_password_reset', '#3', '127.0.0.1', '2026-05-25 11:09:51'),
(15, 7, 'admin@example.com', 'user_create', '#8: pk@gmail.com (user)', '127.0.0.1', '2026-05-25 11:27:58'),
(16, 7, 'admin@example.com', 'video_delete', '#7', '127.0.0.1', '2026-05-29 06:32:52'),
(17, 7, 'admin@example.com', 'video_delete', '#6', '127.0.0.1', '2026-05-29 06:32:55'),
(18, 7, 'admin@example.com', 'video_delete', '#4', '127.0.0.1', '2026-05-29 06:32:58'),
(19, 7, 'admin@example.com', 'video_delete', '#5', '127.0.0.1', '2026-05-29 06:33:09'),
(20, 7, 'admin@example.com', 'video_upload', 'Uploaded video #8: sample 5s [cat 1]', '127.0.0.1', '2026-05-29 06:34:48'),
(21, 7, 'admin@example.com', 'file_upload', 'File: 10101015378342_Mar2026.pdf', '127.0.0.1', '2026-05-29 07:03:26'),
(22, 7, 'admin@example.com', 'doc_extract', 'File #50: 1871 words, 9 pages (pdfparser)', '127.0.0.1', '2026-05-29 07:03:57'),
(23, 7, 'admin@example.com', 'file_delete', '#50: 10101015378342_Mar2026.pdf', '127.0.0.1', '2026-05-29 07:06:00'),
(24, 7, 'admin@example.com', 'video_transcribe', '#8: sample 5s (6 sec, 4 chars)', '127.0.0.1', '2026-05-29 07:12:37'),
(25, 7, 'admin@example.com', 'video_delete', '#8', '127.0.0.1', '2026-05-29 08:47:35'),
(26, 7, 'admin@example.com', 'category_delete', '#1', '127.0.0.1', '2026-05-29 08:47:40'),
(27, 7, 'admin@example.com', 'category_delete', '#2', '127.0.0.1', '2026-05-29 08:47:43'),
(28, 7, 'admin@example.com', 'category_delete', '#3', '127.0.0.1', '2026-05-29 08:47:45'),
(29, 7, 'admin@example.com', 'category_delete', '#4', '127.0.0.1', '2026-05-29 08:47:47'),
(30, 7, 'admin@example.com', 'album_delete', '#1: drfterterte', '127.0.0.1', '2026-05-29 08:47:55'),
(31, 7, 'admin@example.com', 'album_delete', '#3: my testion', '127.0.0.1', '2026-05-29 08:47:58'),
(32, 7, 'admin@example.com', 'folder_delete', '#160 (cascade)', '127.0.0.1', '2026-05-29 08:48:03'),
(33, 7, 'admin@example.com', 'folder_delete', '#162 (cascade)', '127.0.0.1', '2026-05-29 08:48:07'),
(34, 7, 'admin@example.com', 'folder_delete', '#161 (cascade)', '127.0.0.1', '2026-05-29 08:48:09'),
(35, 7, 'admin@example.com', 'user_delete', '#1', '127.0.0.1', '2026-05-29 08:48:23');

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_audit_log`
--

INSERT INTO `admin_audit_log` (`id`, `admin_id`, `action`, `details`, `ip`, `created_at`) VALUES
(1, 1, 'login', 'Admin signed in', '127.0.0.1', '2026-05-20 15:21:19'),
(2, 1, 'logout', 'Admin signed out', '127.0.0.1', '2026-05-20 15:37:11'),
(3, 1, 'login', 'Admin signed in', '127.0.0.1', '2026-05-20 15:37:21'),
(4, 1, 'quiz_checkpoint_created', 'Video #4 @ 0.45s â€” bcg', '127.0.0.1', '2026-05-20 15:43:30'),
(5, 1, 'quiz_checkpoint_deleted', 'Checkpoint #33 removed', '127.0.0.1', '2026-05-20 15:43:38'),
(6, 1, 'login', 'Admin signed in', '127.0.0.1', '2026-05-22 10:24:21');

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_attempts`
--

CREATE TABLE `admin_login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `username` varchar(120) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login_attempts`
--

INSERT INTO `admin_login_attempts` (`id`, `ip`, `username`, `success`, `attempted_at`) VALUES
(1, '127.0.0.1', 'admin', 1, '2026-05-20 15:21:19'),
(2, '127.0.0.1', 'ADMIN', 1, '2026-05-20 15:37:21'),
(3, '127.0.0.1', 'admin', 1, '2026-05-22 10:24:21'),
(4, '127.0.0.1', 'admin', 0, '2026-05-22 11:08:39'),
(5, '127.0.0.1', 'admin', 0, '2026-05-22 11:08:42'),
(6, '127.0.0.1', 'admin', 0, '2026-05-22 11:08:46'),
(7, '127.0.0.1', 'admin', 0, '2026-05-22 11:09:01'),
(8, '127.0.0.1', 'admin', 0, '2026-05-22 11:10:02');

-- --------------------------------------------------------

--
-- Table structure for table `ai_file_texts`
--

CREATE TABLE `ai_file_texts` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `extracted_text` longtext DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `item_type` enum('video','album','file') NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookmarks`
--

INSERT INTO `bookmarks` (`id`, `user_id`, `item_type`, `item_id`, `created_at`) VALUES
(1, 8, 'video', 7, '2026-05-27 11:37:22'),
(6, 8, 'file', 49, '2026-05-27 11:38:33'),
(8, 7, 'video', 8, '2026-05-29 08:42:54');

-- --------------------------------------------------------

--
-- Table structure for table `document_extracts`
--

CREATE TABLE `document_extracts` (
  `id` int(10) UNSIGNED NOT NULL,
  `file_id` int(10) UNSIGNED NOT NULL,
  `full_text` longtext NOT NULL,
  `page_count` smallint(5) UNSIGNED DEFAULT 0,
  `word_count` int(10) UNSIGNED DEFAULT 0,
  `extractor` varchar(40) DEFAULT 'pdfparser',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_extracts`
--

INSERT INTO `document_extracts` (`id`, `file_id`, `full_text`, `page_count`, `word_count`, `extractor`, `created_at`) VALUES
(1, 50, '[Page 1]\nAbove values are tax exclusive\nRASHMI KANT\nRegistered Email:\nRASHMISAHA5@GMAIL.COM\nRegistered Telephone Number (RTN):\n9811257878\nYour Plan:\nAirtel Black\nAirtel Black ID\n10101015378342\nNumber of connections\n5\nStatement Date\n12 Mar 2026\nStatement Period\n11 Feb 2026 - 10 Mar 2026\nTotal Amount Payable:\nâ‚¹3,450.32\nDue Date:\n22 Mar 2026\nPay via\nAirtel Thanks App\nwww.airtel.in/pay\nScan and pay via any UPI apps\nPowered by Â \nLast bill amount Payment made Credits This Month\'s Charges Total Amount Amount after\ndue date (22 Mar)\nâ‚¹2,476.82 -â‚¹2,476.82 -â‚¹0.00+â‚¹3,450.32	=â‚¹3,450.32 â‚¹3,686.32\nThis Month\'s Summary	(Amounts in â‚¹)\nServices	Connections Plan/Pack Charges Other Charges Total\nAirtel Black - 10101015378342	5	2198.0	925.00 3,123.00\nPlan Discount	-	-	-	199.00\nRevised Charges	-	-	-	2,924.00\nTaxes	-	-	-	526.32\nThis month\'s charges	3,450.32\nTOTAL	â‚¹3,450.32\nTotal: Three Thousand Four Hundred Fifty Rupees And Thirty Two Paise Only\nChanges This Month\nServices	Details	Amount (â‚¹)\nInternational Roaming Charges\nMobile : 9873815551 IR Usage beyond pack (A)	925.00\nTotal International Roaming Charges (A)	925.00\nDetailed break-up of above charges can be found in bill\nBills & Payments Summary\nMonth	Previous Dues (A) Payments (B) Credits (C)This Month\'s Charges (D) Total Amount (A+B+C+D)\nMar\'26	2,476.82 -2,476.82 0.00	3,450.32	3,450.32\nFeb\'26	2,358.82 -2,358.82 0.00	2,476.82	2,476.82\nJan\'26	2,362.36 -2,362.36 0.00	2,358.82	2,358.82\nDec\'25	2,358.82 -2,358.82 0.00	2,362.36	2,362.36\nAll above values are inclusive of tax\nBlack Monthly Statement\n\n[Page 2]\nFIXEDLINE AND Wi-Fi SERVICES\nOriginal Copy for Recipient provided by  Bharti Airtel Limited- Tax Invoice\nFixedline number :  01141811788 / Wi-Fi ID :  01188128582_dsl\nBilling Address\n \nMrs RASHMI KANT\nC7/9, 3rd Floor, Boxfit Vasant Vihar, Block C, Vasant Vihar, New\nDelhi Block C Vasant Vihar Near Vasant Vihar Market Delhi\n110057, New Delhi Delhi, India\nDelhi, 110057\nDelhi\nEmail id : RASHMISAHA5@GMAIL.COM\nPhoneNo:9811257878\nHF2607I012378570                          7027767348\nShip To State Code : 07	Place of Supply : Delhi\n \nAccount\n \nAccount No\n7027767348\nBill Period	11 Feb 2026 to 10 Mar 2026\nBill NO\nHF2607I012378570\nBill Date	12 Mar 2026\nDue date	22 Mar 2026\nCredit limit	3000.00\nSecurity deposit	0.00\n        \nLast bill amountPayment madeCreditsThis month\'s chargesTotal Amount\nAmount after due\ndate(22Mar)\n` 944.00-` 944.00-` 0.00+` 944.00=` 944.00` 1062.00\n        \nThis Month\'s Charges	Charges( ` )\n Rental Charges	800.00Taxes	144.00Total Amount	` 944.00Total:Nine Hundred Forty Four Rupees and Zero Paise Only \nDetailed breakup of these charges can be found on next page\nSend payment to\n7027767348.FL@mairtel\nScan & pay via any UPI Apps\nPowered by\nFor Bharti Airtel Limited\nVasim Unissa S,\nHead - Experience Operations (VP) Page 1 of 2\n\n[Page 3]\nYOUR CHARGES IN DETAIL\nRelationship No  \n:7027767348\n \nPayment Modes - Pay online using debit/credit card, netbanking on My Airtel App, www.airtel.in, eWallets, UPI, visit an Airtel Store to pay using cash/cheque/credit/\ndebit cards or activate Auto pay options from bank account (NACH) or Credit card account (SI)\nContact Information - For Queries: Call 121 (toll free for Airtel), 011-44444121(for Non-Airtel number, call charges apply) | Complaints: Call 198 (toll free for Airtel),\n011-44444198(for Non-Airtel number, call charges apply) | NDNC Registration: Call 1909 (Activation time: 7 days) | Complaint/SR Status: www.airtel.in/help. |\nAppellate Desk: Mr. Ankur Singh, 011-41614690; appellate.ncr@in.airtel.com; address: Bharti Airtel Limited, Plot No. 16, Udyog Vihar, Phase - IV, Gurgaon - 122015\nCall 1930 for cyber-crime fraud reporting.\nCorporate Coordinator Contact Information - For queries and complaints: Call 1800102002 | Email: Esupport@in.airtel.com\nCharges - Itemized bill: Rs. 50/Bill | Duplicate Bill: Rs. 50/Bill (Last 2 months free) | Cheque / SI / ECS Decline: Rs. 200 | Late payment charges shall be Rs. 100 for\nbill value between Rs. 300 and Rs. 5,000; and for bill value above Rs. 5,000, a charge of 2% of the amount, capped at a maximum of Rs. 750 will be applied. As per\nthe Government directive, effective 1-July-17, 18% GST is applicable on Late Fee Charges. No charge is levied for any service without your explicit consent.\nAddress change - Visit the nearest Airtel Store with new address proof. For store details, visit www.airtel.in/store\nOther Information - Tariff Plan: No increase in any line item (except ISD) for first 6months effective enrolment date. T&C apply | No fee is charged for migrating to\nany plan | Disconnection: For permanent disconnection, security deposit will be refunded within 60days. Else, interest will be paid @10%p.a. | Call pulses will be\nrounded off | Billing disagreements should be reported within 2months of bill receipt. Post this period no claim shall be entertained. | Whether tax is payable on\nReverse Charge Basis - \"NO\". The Airtel Wi-Fi router is Airtel\'s property & may be reclaimed if the customer discontinues Airtel\'s Wi-Fi services.\nRegistered Office : Bharti Airtel Limited, Plot No. 16, Udyog Vihar, Phase IV, Gurugram - 122015, Haryana, India. Tel: +91-124-4248655, e-mail: 121@in.airtel.com,\nwebsite: www.airtel.in\nCorporate Identity Number : L74899HR1995PLC095967 Bharti Airtel Ltd, 1, Bharti Crescent, Nelson Mandela Road, Vasant Kunj, Phase II,Delhi, Delhi- 110070\nShip To State Code : 7 GST registration no : 07AAACB2894G1ZP under Category TELECOMMUNICATION SERVICE   PAN : AAACB2894G\nHSN : 998412 Fixed Telephony Service , 998433 On-line video content , 996812 Courier Services , 997317 Leasing or rental services concerning\ntelecommunications equipment with or without operator , 9983 Support services , 998716 Maintenance and repair services of telecommunication equipment and\napparatus , 999799 Other Services n.e.c\nRentalsTotal(`)\nDescription\nFrom date To date Rental Discount Net chargesPlan DetailsScheme Charges @  `  99911/02/202610/03/2026999.00199.00800.00\n800.00\nTax Details\nTotal(`)\nCGST	SGST/UTGST\nHSNTaxable Value\nRateAmountRateAmount\nTotal Tax\n998412800.00\n9%\n72.00\n9%\n72.00144.00144.00This month\'s charges944.00\nPayments and refunds-details\nDescriptionDateAmountTotal(`)\npayment via airtel pay (payu)\n25-Feb-2026	-944.00-944.00\n  Bill Plan Details : 999 WiFi_200Mbps\n  Rental:  `  999.00 Quota: Unlimited *Speed: 200 Mbps\n( 999.00 Rental includes Rs.749 towards Wi-Fi & Fixed Line Plan  and Rs.250 towards Platform Services )  \n   Voice - call rates: Unlimited local and STD calls\n   ISD - call rates: for country specific rates visit www.airtel.in\n   *Post consumption of Unlimited quota, the speed would be revised to 2 Mbps\n   For information on other plans, visit www.airtel.in/broadband Page 2 of 2\n\n[Page 4]\nMOBILE SERVICES\nOriginal Copy for Recipient provided by  Bharti Airtel Limited- Tax Invoice\n1-2828870326692\nBilling Address\nMs. Rashmi kant\nF5/3 vasant vihar south west delhi f block NEW DELHI\nNew Delhi 110057\nDelhi\nEmail: rashmi_kant_2000@yahoo.com\nPhoneNo: 9811257878\nMF2607I012950203                          1-2828870326692\nShip To State Code : 07	Place of Supply : Delhi\nAccount\nAccount No	1-2828870326692\nBill Period	11 Feb 2026-10 Mar 2026\nBill NO	MF2607I012950203\nAdjustment	0.00\nBill Date	12 Mar 2026\nDue date	22 Mar 2026\nCredit limit	7500\nSecurity deposit	0.0\n        \nLast bill amountPayment madeCreditsThis Month\'s ChargesTotal Amount\nAmount after due\ndate(22Mar)\n` 1532.82-` 1532.82-` 0.00+` 2506.32=` 2506.32` 2624.32\n        \nThis Month\'s Charges	Charges (` )\nRental Charges	1199.00Usage	925.00Taxes	382.32Total Amount	` 2506.32Total:Two Thousand Five Hundred Six Rupees and Thirty Two Paise Only\nDetailed breakup of these charges can be found on next page\nSend payment to\n1-2828870326692.POST@mairtel\nScan & pay via any UPI Apps\nPowered by\nFor Bharti Airtel Limited\nVasim Unissa S,\nHead - Experience Operations (VP) Page 1 of 6\n\n[Page 5]\n1-2828870326692Relationship number\nSUMMARY OF THIS MONTH CHARGES\n \n \nPayment Modes - Pay online using debit/credit card, netbanking on My Airtel App, www.airtel.in, eWallets, UPI, visit an Airtel Store to pay using\ncash/cheque/credit/debit cards or activate Auto pay options from bank account (NACH) or Credit card account (SI) \nContact Information - For Queries: Call 121(tollfree) | Complaints: Call 198(tollfree) | Email: 121@airtel.com | NDNC\nRegistration: Call 1909 (Activation time: 7days) | Complaint / SR Status: www.airtel.in/airtelpresence . Appellate Desk: Mr. Ankur\nSingh ;9958444865;appellate.del@in.airtel.com ;Bharti Airtel Limited, Plot No. 16, Udyog Vihar, Phase - IV, Gurgaon - 122015\nCall 1930 for cyber-crime fraud reporting.\nCharges -  Cheque / SI / ECS Decline: Rs. 200 | Late payment charges shall be Rs. 100 for bill value between Rs. 300 and Rs. 5,000; and for\nbill value above Rs. 5,000, a charge of 2% of the amount, capped at a maximum of Rs. 750 will be applied. No charge is levied for any service\nwithout your explicit consent.\nAddress change -  Visit the nearest Airtel Store with new address proof.For store details, visit www.airtel.in/store\nOther Information -  Tariff Plan: No increase in any line item (except ISD) for first 6months effective enrolment date. T&C apply | No fee is\ncharged for migrating to any plan | Disconnection: For permanent disconnection, security deposit will be refunded within 60days. Else, interest\nwill be paid @10%p.a. | Call pulses will be rounded off | Billing disagreements should be reported within 2months of bill receipt. Post this period\nno claim shall be entertained. |  The credit limit is not applicable on usage done in international roaming. | As per the Government directive,\neffective 1-July-17, existing service tax of 15% has been replaced with 18% GST. |Whether tax is payable on Reverse Charge Basis - \"NO\".\nRegistered Office : Bharti Airtel Limited, Plot No. 16, Udyog Vihar, Phase IV, Gurugram - 122015, Haryana, India. Tel: +91-124-4248655, e-mail:\n121@in.airtel.com, website: www.airtel.in\nCorporate Identity Number: L74899HR1995PLC095967 Bharti Airtel Ltd, 1, Bharti Crescent, Nelson Mandela Road, Vasant Kunj, Phase\nII,Delhi, Delhi- 110070\nState Code: 07 GST registration no.:   07AAACB2894G1ZP under Category TELECOMMUNICATION SERVICE PAN: AAACB2894G\nHSN: 998599 Other support services 998433 On-line video content 996812 Courier Services 997317 Leasing or rental services concerning\ntelecommunications equipment with or without operator 998413 Mobile Telecommunication Service 9983 Support services 998716\nMaintenance and repair services of telecommunication equipment and apparatus 999799 Other Services n.e.c\n \nAccount summaryAccount no.  Airtel numberMonthly rentalsUsage One time chargesTotal   1-2828870326692 98112578781199.000.000.001199.001-2884374553892 97115310980.000.000.000.001-2884377640955 98738155510.00925.000.00925.001-2884373141627 98101875640.000.000.000.00\nTotal 1199.00 925.000.002124.00  \nTax Details\nTotal(`)\nCGST	SGST/UTGST\nHSNTaxable Value\nRateAmountRateAmount\nTotal Tax\n9984132124.00\n9%\n191.16\n9%\n191.16382.32382.32This month\'s charges2506.32\nPayment Details\nDescriptionDateTotal\nTotal(`)\nPayment via Airtel Pay (PayU)	25-Feb-2026	-1532.82-1532.82 Page 2 of 6\n\n[Page 6]\nYOUR CHARGES IN DETAIL - 9811257878\nRelationship number\nAirtel mobile number\n1-2828870326692\n9811257878\nMonthly rentals\nTotal(`)Description	From date To date Rental Discount AmountPlan Nameinfinity family 1199 plan_homes_pkg_5107911/02/202610/03/20261199.000.001199.00\n1199.00\nThis month\'s charges1199.00\nTariff after plan benefits\nSMS rates	Local( ` ) National(  ` )local/national	0.1/msg 0.1/msg\nnational roaming	0.25/msg 0.38/msg\ninternational	5/msg 5/msg\nCall rates	Local( ` ) STD(` )to airtel mobile00/min00/min\nto other mobile	00/min 00/min\nto landline	00/min 00/min\nto airtel cug	00/min 00/min\nvideo call	00/sec 00/sec\nFor Roaming, ISD and other plans/tariff, visit www.airtel.in\n Data conversion : 1MB =1,024KB ; 1GB=1,024MB/1,048,576KB Page 3 of 6\n\n[Page 7]\nYOUR CHARGES IN DETAIL - 9711531098\nRelationship number\nAirtel mobile number\n1-2884374553892\n9711531098\nMonthly rentals\nTotal(`)Description	From date To date Rental Discount Amount\n0.00\nThis month\'s charges0.00\nTariff after plan benefits\nSMS rates	Local( ` ) National(  ` )local/national	0.1/msg 0.1/msg\nnational roaming	0.25/msg 0.38/msg\ninternational	5/msg 5/msg\nCall rates	Local( ` ) STD(` )to airtel mobile00/min00/min\nto other mobile	00/min 00/min\nto landline	00/min 00/min\nto airtel cug	00/min 00/min\nvideo call	00/sec 00/sec\nFor Roaming, ISD and other plans/tariff, visit www.airtel.in\n Data conversion : 1MB =1,024KB ; 1GB=1,024MB/1,048,576KB Page 4 of 6\n\n[Page 8]\nYOUR CHARGES IN DETAIL - 9873815551\nRelationship number\nAirtel mobile number\n1-2884377640955\n9873815551\nMonthly rentals\nTotal(`)Description	From date To date Rental Discount Amount\n0.00\nInternational Roaming\nUsage Charges - (Duration of IR Period)MB UsedTotal UsageChargeable usageAmountTotal(`)SMS	-	1	1	25.00 Incoming Calls - Voice- 9 9900.00 \n925.00 \n \nInternational Roaming Net ChargesThis month\'s charges925.00\nTariff after plan benefits\nSMS rates	Local( ` ) National(  ` )local/national	0.1/msg 0.1/msg\nnational roaming	0.25/msg 0.38/msg\ninternational	5/msg 5/msg\nCall rates	Local( ` ) STD(` )to airtel mobile00/min00/min\nto other mobile	00/min 00/min\nto landline	00/min 00/min\nto airtel cug	00/min 00/min\nvideo call	00/sec 00/sec\nFor Roaming, ISD and other plans/tariff, visit www.airtel.in\n Data conversion : 1MB =1,024KB ; 1GB=1,024MB/1,048,576KB Page 5 of 6\n\n[Page 9]\nYOUR CHARGES IN DETAIL - 9810187564\nRelationship number\nAirtel mobile number\n1-2884373141627\n9810187564\nMonthly rentals\nTotal(`)Description	From date To date Rental Discount Amount\n0.00\nThis month\'s charges0.00\nTariff after plan benefits\nSMS rates	Local( ` ) National(  ` )local/national	0.1/msg 0.1/msg\nnational roaming	0.25/msg 0.38/msg\ninternational	5/msg 5/msg\nCall rates	Local( ` ) STD(` )to airtel mobile00/min00/min\nto other mobile	00/min 00/min\nto landline	00/min 00/min\nto airtel cug	00/min 00/min\nvideo call	00/min 00/min\nFor Roaming, ISD and other plans/tariff, visit www.airtel.in\n Data conversion : 1MB =1,024KB ; 1GB=1,024MB/1,048,576KB Page 6 of 6', 9, 1871, 'pdfparser', '2026-05-29 07:03:56');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `file_id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `video_link` varchar(1000) DEFAULT NULL,
  `file_desc` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `albumid` int(11) NOT NULL,
  `name` varchar(225) NOT NULL,
  `adesc` varchar(225) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'active',
  `parent_folder_id` int(11) DEFAULT NULL,
  `folder_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery_video`
--

CREATE TABLE `gallery_video` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(200) NOT NULL,
  `des` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gallery_video`
--

INSERT INTO `gallery_video` (`id`, `name`, `title`, `des`) VALUES
(1, 'sample-5s_20260525_063825.mp4', 'dsfsdg', 'sdgsg');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip`, `email`, `success`, `attempted_at`) VALUES
(1, '127.0.0.1', 'anshika@gmail.com', 1, '2026-05-22 10:15:51'),
(2, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-22 11:01:19'),
(3, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-22 11:01:36'),
(4, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-22 11:02:26'),
(5, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-22 11:04:30'),
(6, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-22 11:38:05'),
(7, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-22 11:38:14'),
(8, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-22 12:36:47'),
(9, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-22 13:17:06'),
(10, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-25 10:04:38'),
(11, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-25 10:04:51'),
(12, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-25 10:05:32'),
(13, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-25 16:36:07'),
(14, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-25 16:36:17'),
(15, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-25 16:36:29'),
(16, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:37:19'),
(17, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:37:28'),
(18, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:37:34'),
(19, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:37:44'),
(20, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:37:52'),
(21, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:37:57'),
(22, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:38:52'),
(23, '127.0.0.1', 'anshika@gmail.com', 0, '2026-05-25 16:39:04'),
(24, '127.0.0.1', 'priyanka@gmail.com', 0, '2026-05-25 16:48:43'),
(25, '127.0.0.1', 'pk@gmail.com', 0, '2026-05-25 16:49:52'),
(26, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-25 17:00:00'),
(27, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-26 09:28:55'),
(28, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-26 12:53:20'),
(29, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-27 10:11:54'),
(30, '127.0.0.1', 'pk@gmail.com', 1, '2026-05-29 10:28:11');

-- --------------------------------------------------------

--
-- Table structure for table `news_user`
--

CREATE TABLE `news_user` (
  `id` int(11) NOT NULL,
  `email` varchar(225) NOT NULL,
  `password` varchar(225) NOT NULL,
  `name` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news_user`
--

INSERT INTO `news_user` (`id`, `email`, `password`, `name`) VALUES
(1, 'pk@gmail.com', '12345', 'priyanka'),
(2, 'ns@gmail.com', '123', 'Neha'),
(3, 'rt@gmail.com', '12345', 'Rita');

-- --------------------------------------------------------

--
-- Table structure for table `new_post`
--

CREATE TABLE `new_post` (
  `id` int(11) NOT NULL,
  `title` varchar(225) NOT NULL,
  `content` varchar(225) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `date` date NOT NULL DEFAULT current_timestamp(),
  `progress` varchar(225) NOT NULL,
  `project` varchar(225) NOT NULL,
  `cat` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `new_post`
--

INSERT INTO `new_post` (`id`, `title`, `content`, `user_id`, `start_time`, `end_time`, `date`, `progress`, `project`, `cat`) VALUES
(1, 'anything', 'abcd', 1, '25:52:45', '26:52:45', '2024-12-10', 'completed', 'abc', 'caca'),
(2, 'Configuration Management', 'sdwsfr', 1, '09:00:00', '10:00:00', '2024-12-10', 'Completed', 'CMS 24-29', 'Process'),
(3, 'Bug Triage', 'qweqw', 1, '23:45:00', '23:54:00', '2025-01-07', 'Completed', 'CMS 24-29', 'Testing'),
(5, 'Configuration Management', 'gfgfszdv', 1, '09:09:00', '10:00:00', '2025-02-06', 'Completed', 'CMS 24-29', 'Process'),
(6, 'Appraisal', 'JHGH', 1, '15:45:00', '16:56:00', '2025-02-10', 'Completed', 'CMS 24-29', 'Process');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Recipient',
  `type` varchar(40) NOT NULL COMMENT 'video_new, album_new, doc_new, quiz_new, etc',
  `title` varchar(200) NOT NULL COMMENT 'Short heading',
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(300) DEFAULT NULL COMMENT 'Where the bell click takes them',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link`, `is_read`, `created_at`) VALUES
(1, 1, 'video_new', 'New video: sample 5s', 'that is my first video for demo', '../Videos/video_player.php?id=8', 0, '2026-05-29 06:34:48'),
(2, 8, 'video_new', 'New video: sample 5s', 'that is my first video for demo', '../Videos/video_player.php?id=8', 0, '2026-05-29 06:34:48'),
(4, 1, 'doc_new', 'New document: 10101015378342_Mar2026.pdf', '', '../Documents/view_file.php?file_id=21', 0, '2026-05-29 07:03:26'),
(5, 8, 'doc_new', 'New document: 10101015378342_Mar2026.pdf', '', '../Documents/view_file.php?file_id=21', 0, '2026-05-29 07:03:26');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `mile_phase` varchar(225) NOT NULL,
  `task` varchar(225) NOT NULL,
  `sub_task` varchar(225) NOT NULL,
  `plan_start` date NOT NULL,
  `plan_end` date NOT NULL,
  `plan_effort` varchar(225) NOT NULL,
  `res_allocated` varchar(225) NOT NULL,
  `act_item` varchar(225) NOT NULL,
  `remarks` varchar(225) NOT NULL,
  `status` varchar(225) NOT NULL,
  `task_des` varchar(225) NOT NULL,
  `user_id` int(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_options`
--

CREATE TABLE `quiz_options` (
  `id` int(10) UNSIGNED NOT NULL,
  `quiz_id` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(512) NOT NULL,
  `option_b` varchar(512) NOT NULL,
  `option_c` varchar(512) DEFAULT NULL,
  `option_d` varchar(512) DEFAULT NULL,
  `correct_option` tinyint(1) NOT NULL DEFAULT 0,
  `explanation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_responses`
--

CREATE TABLE `quiz_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quiz_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `option_id` int(10) UNSIGNED NOT NULL,
  `user_ip` varchar(50) DEFAULT NULL,
  `user_session` varchar(100) DEFAULT NULL,
  `user_name` varchar(120) DEFAULT NULL,
  `group_name` varchar(120) DEFAULT NULL,
  `chosen_option` tinyint(1) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `time_taken_sec` decimal(6,2) DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_album`
--

CREATE TABLE `tbl_album` (
  `albumid` int(11) NOT NULL,
  `name` varchar(225) NOT NULL,
  `adesc` varchar(225) NOT NULL,
  `image` varchar(225) NOT NULL,
  `event_date` date DEFAULT NULL,
  `date` varchar(225) NOT NULL,
  `status` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_gallery`
--

CREATE TABLE `tbl_gallery` (
  `gid` int(11) NOT NULL,
  `aid` varchar(255) NOT NULL,
  `gname` varchar(255) NOT NULL,
  `gimages` varchar(225) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `date` varchar(225) NOT NULL,
  `status` varchar(225) NOT NULL,
  `name` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login`
--

CREATE TABLE `tbl_login` (
  `lid` int(11) NOT NULL,
  `username` varchar(225) NOT NULL,
  `password` varchar(255) NOT NULL,
  `type` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_login`
--

INSERT INTO `tbl_login` (`lid`, `username`, `password`, `type`) VALUES
(1, 'admin', '$2y$10$zUXOH/cWL0nJfJUofhmCsejKkQZf/szHlZyZXqVWq25/nfwYfPWv2', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `group_name` varchar(80) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `group_name`, `role`, `created_at`, `last_login`) VALUES
(7, 'admin@example.com', '$2y$10$oRDSL.URd9Ocz/Xy1fHSLepGw/s5E9ce3sdryda72ZkGMqNm4H2Py', 'Administrator', NULL, 'admin', '2026-05-22 13:14:31', NULL),
(8, 'pk@gmail.com', '$2y$10$PehBAW5fQMwfbVPi3iLtzO6HWVPtB2lur5Sfu4ZZO9iU7HQt50lrm', 'priyanka', 'ss', 'user', '2026-05-25 16:57:58', '2026-05-29 10:28:11');

-- --------------------------------------------------------

--
-- Table structure for table `video`
--

CREATE TABLE `video` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `des` text DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_categories`
--

CREATE TABLE `video_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_progress`
--

CREATE TABLE `video_progress` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `last_position` float NOT NULL DEFAULT 0 COMMENT 'Seconds into the video',
  `duration_sec` float NOT NULL DEFAULT 0 COMMENT 'Total length (snapshot at last save)',
  `progress_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `completed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 once watched past 90%',
  `last_watched_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `video_progress`
--

INSERT INTO `video_progress` (`id`, `user_id`, `video_id`, `last_position`, `duration_sec`, `progress_pct`, `completed`, `last_watched_at`) VALUES
(70, 7, 8, 0, 5.8, 0, 0, '2026-05-29 09:07:39');

-- --------------------------------------------------------

--
-- Table structure for table `video_quizzes`
--

CREATE TABLE `video_quizzes` (
  `id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `trigger_time` decimal(10,2) NOT NULL,
  `group_label` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_summaries`
--

CREATE TABLE `video_summaries` (
  `id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `summary` text NOT NULL,
  `key_topics` text DEFAULT NULL,
  `model` varchar(40) DEFAULT 'llama-3.1-8b-instant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_transcripts`
--

CREATE TABLE `video_transcripts` (
  `id` int(10) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `full_text` longtext NOT NULL,
  `segments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Word/sentence timestamps from Whisper' CHECK (json_valid(`segments`)),
  `language` varchar(10) DEFAULT 'en',
  `duration_sec` int(10) UNSIGNED DEFAULT 0,
  `model` varchar(40) DEFAULT 'whisper-large-v3-turbo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitore`
--

CREATE TABLE `visitore` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `url_visited` text NOT NULL,
  `reference_url` text DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `browser_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `count` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_audit`
--
ALTER TABLE `admin_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `ai_file_texts`
--
ALTER TABLE `ai_file_texts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_item` (`user_id`,`item_type`,`item_id`),
  ADD KEY `idx_user_recent` (`user_id`,`created_at`);

--
-- Indexes for table `document_extracts`
--
ALTER TABLE `document_extracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_file` (`file_id`);
ALTER TABLE `document_extracts` ADD FULLTEXT KEY `ft_text` (`full_text`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `folder_id` (`folder_id`);
ALTER TABLE `files` ADD FULLTEXT KEY `ft_filedesc` (`file_name`,`file_desc`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`albumid`);
ALTER TABLE `folders` ADD FULLTEXT KEY `ft_name_desc` (`name`,`adesc`);

--
-- Indexes for table `gallery_video`
--
ALTER TABLE `gallery_video`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `news_user`
--
ALTER TABLE `news_user`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `new_post`
--
ALTER TABLE `new_post`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quiz_options`
--
ALTER TABLE `quiz_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_video_question` (`user_name`(100),`video_id`,`option_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `user_session` (`user_session`),
  ADD KEY `answered_at` (`answered_at`),
  ADD KEY `idx_user_name` (`user_name`),
  ADD KEY `idx_group_name` (`group_name`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `tbl_album`
--
ALTER TABLE `tbl_album`
  ADD PRIMARY KEY (`albumid`);
ALTER TABLE `tbl_album` ADD FULLTEXT KEY `ft_namedesc` (`name`,`adesc`);

--
-- Indexes for table `tbl_gallery`
--
ALTER TABLE `tbl_gallery`
  ADD PRIMARY KEY (`gid`);

--
-- Indexes for table `tbl_login`
--
ALTER TABLE `tbl_login`
  ADD PRIMARY KEY (`lid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `video`
--
ALTER TABLE `video`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `video_categories`
--
ALTER TABLE `video_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_slug` (`slug`);

--
-- Indexes for table `video_progress`
--
ALTER TABLE `video_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_video` (`user_id`,`video_id`),
  ADD KEY `idx_user_recent` (`user_id`,`last_watched_at`);

--
-- Indexes for table `video_quizzes`
--
ALTER TABLE `video_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `trigger_time` (`trigger_time`);

--
-- Indexes for table `video_summaries`
--
ALTER TABLE `video_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_video` (`video_id`);

--
-- Indexes for table `video_transcripts`
--
ALTER TABLE `video_transcripts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_video` (`video_id`);
ALTER TABLE `video_transcripts` ADD FULLTEXT KEY `ft_text` (`full_text`);

--
-- Indexes for table `visitore`
--
ALTER TABLE `visitore`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit`
--
ALTER TABLE `admin_audit`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `admin_login_attempts`
--
ALTER TABLE `admin_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ai_file_texts`
--
ALTER TABLE `ai_file_texts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `document_extracts`
--
ALTER TABLE `document_extracts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `albumid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `gallery_video`
--
ALTER TABLE `gallery_video`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `news_user`
--
ALTER TABLE `news_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `new_post`
--
ALTER TABLE `new_post`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_options`
--
ALTER TABLE `quiz_options`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `quiz_responses`
--
ALTER TABLE `quiz_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `tbl_album`
--
ALTER TABLE `tbl_album`
  MODIFY `albumid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_gallery`
--
ALTER TABLE `tbl_gallery`
  MODIFY `gid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_login`
--
ALTER TABLE `tbl_login`
  MODIFY `lid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `video`
--
ALTER TABLE `video`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `video_categories`
--
ALTER TABLE `video_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `video_progress`
--
ALTER TABLE `video_progress`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `video_quizzes`
--
ALTER TABLE `video_quizzes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `video_summaries`
--
ALTER TABLE `video_summaries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `video_transcripts`
--
ALTER TABLE `video_transcripts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `visitore`
--
ALTER TABLE `visitore`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_file_texts`
--
ALTER TABLE `ai_file_texts`
  ADD CONSTRAINT `ai_file_texts_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`albumid`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
