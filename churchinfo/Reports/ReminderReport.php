<?php
/*******************************************************************************
*
*  filename    : Reports/ReminderReport.php
*  last change : 2003-08-30
*  description : Creates a PDF of the current deposit slip
*
*  InfoCentral is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";

//Get the Fiscal Year ID out of the querystring
$iFYID = FilterInput($_GET["FYID"],'int');

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}

class PDF_ReminderReport extends ChurchInfoReport {

	// Constructor
	function PDF_ReminderReport() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetFont('Times','', 10);
		$this->SetMargins(0,0);
		$this->Open();
		$this->SetAutoPageBreak(false);
	}

	function StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iFYID) {
		$curY = $this->StartLetterPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iYear);
		$curY += 2 * $this->incrementY;
		$blurb = $this->sReminder1 . (1995 + $iFYID) . "/" . (1995 + $iFYID + 1) . ".";
		$this->WriteAt ($this->leftX, $curY, $blurb);
		$curY += 2 * $this->incrementY;
		return ($curY);
	}

	function FinishPage ($curY) {
		$curY += 2 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, "Sincerely,");
		$curY += 4 * $this->incrementY;
		$this->WriteAt ($this->leftX, $curY, $this->sReminderSigner);
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ReminderReport();

// Get all the families
$sSQL = "SELECT * FROM family_fam WHERE 1";
$rsFamilies = RunQuery($sSQL);

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies)) {
	extract ($aFam);

	// Get pledges and payments for this family and this fiscal year
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . " ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

// If there is either a pledge or a payment add a page for this reminder report

	if (mysql_num_rows ($rsPledges) == 0)
		continue;

	$curY = $pdf->StartNewPage ($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country, $iFYID);

	// Get pledges only
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . " AND plg_PledgeOrPayment = 'Pledge' ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

	$totalAmountPledges = 0;
	if (mysql_num_rows ($rsPledges) == 0) {
		$curY += $summaryIntervalY;
		$pdf->WriteAt ($summaryDateX, $curY, $this->sReminderNoPledge);
		$curY += 2 * $summaryIntervalY;
	} else {

		$summaryDateX = $pdf->leftX;
		$summaryFundX = 45;
		$summaryAmountX = 80;

		$summaryDateWid = $summaryFundX - $summaryDateX;
		$summaryFundWid = $summaryAmountX - $summaryFundX;
		$summaryAmountWid = 15;

		$summaryIntervalY = 4;

		$curY += $summaryIntervalY;
		$pdf->SetFont('Times','B', 10);
		$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Pledge');
		$curY += $summaryIntervalY;

		$pdf->SetFont('Times','B', 10);

		$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Date');
		$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, 'Fund');
		$pdf->WriteAtCell ($summaryAmountX, $curY, $summaryAmountWid, 'Amount');

		$curY += $summaryIntervalY;

		$totalAmount = 0;
		$cnt = 0;
		while ($aRow = mysql_fetch_array($rsPledges)) {
			extract ($aRow);
			$pdf->SetFont('Times','', 10);

			$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, $plg_date);
			$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, $fundName);

			$pdf->SetFont('Courier','', 8);

			$pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $plg_amount);

			$totalAmount += $plg_amount;
			$cnt += 1;

			$curY += $summaryIntervalY;
		}
		$pdf->SetFont('Times','', 10);
		if ($cnt > 1) {
			$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, "Total pledges");
			$pdf->SetFont('Courier','', 8);
			$totalAmountStr = sprintf ("%.2f", $totalAmount);
			$pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $totalAmountStr);
			$curY += $summaryIntervalY;
		}
		$totalAmountPledges = $totalAmount;
	}

	// Get payments only
	$sSQL = "SELECT *, b.fun_Name AS fundName FROM pledge_plg 
			 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
			 WHERE plg_FamID = " . $fam_ID . " AND plg_FYID = " . $iFYID . " AND plg_PledgeOrPayment = 'Payment' ORDER BY plg_date";
	$rsPledges = RunQuery($sSQL);

	$totalAmountPayments = 0;
	if (mysql_num_rows ($rsPledges) == 0) {
		$curY += $summaryIntervalY;
		$pdf->WriteAt ($summaryDateX, $curY, $this->sReminderNoPayments);
		$curY += 2 * $summaryIntervalY;
	} else {
		$summaryDateX = $pdf->leftX;
		$summaryCheckNoX = 40;
		$summaryMethodX = 60;
		$summaryFundX = 85;
		$summaryMemoX = 120;
		$summaryAmountX = 170;
		$summaryIntervalY = 4;

		$summaryDateWid = $summaryCheckNoX - $summaryDateX;
		$summaryCheckNoWid = $summaryMethodX - $summaryCheckNoX;
		$summaryMethodWid = $summaryFundX - $summaryMethodX;
		$summaryFundWid = $summaryMemoX - $summaryFundX;
		$summaryMemoWid = $summaryAmountX - $summaryMemoX;
		$summaryAmountWid = 15;

		$curY += $summaryIntervalY;
		$pdf->SetFont('Times','B', 10);
		$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Payments');
		$curY += $summaryIntervalY;

		$pdf->SetFont('Times','B', 10);

		$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, 'Date');
		$pdf->WriteAtCell ($summaryCheckNoX, $curY, $summaryCheckNoWid, 'Chk No.');
		$pdf->WriteAtCell ($summaryMethodX, $curY, $summaryMethodWid, 'PmtMethod');
		$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, 'Fund');
		$pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, 'Memo');
		$pdf->WriteAtCell ($summaryAmountX, $curY, $summaryAmountWid, 'Amount');

		$curY += $summaryIntervalY;

		$totalAmount = 0;
		$cnt = 0;
		while ($aRow = mysql_fetch_array($rsPledges)) {
			extract ($aRow);
			$pdf->SetFont('Times','', 10);

			$pdf->WriteAtCell ($summaryDateX, $curY, $summaryDateWid, $plg_date);
			$pdf->PrintRightJustifiedCell ($summaryCheckNoX, $curY, $summaryCheckNoWid, $plg_CheckNo);
			$pdf->WriteAtCell ($summaryMethodX, $curY, $summaryMethodWid, $plg_method);
			$pdf->WriteAtCell ($summaryFundX, $curY, $summaryFundWid, $fundName);
			$pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, $plg_comment);

			$pdf->SetFont('Courier','', 8);

			$pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $plg_amount);

			$totalAmount += $plg_amount;
			$cnt += 1;

			$curY += $summaryIntervalY;
				
			if ($curY > 220) {
				$pdf->AddPage ();
				$curY = 20;
			}

		}
		$pdf->SetFont('Times','', 10);
		if ($cnt > 1) {
			$pdf->WriteAtCell ($summaryMemoX, $curY, $summaryMemoWid, "Total payments");
			$pdf->SetFont('Courier','', 8);
			$totalAmountString = sprintf ("%.2f", $totalAmount);
			$pdf->PrintRightJustifiedCell ($summaryAmountX, $curY, $summaryAmountWid, $totalAmountString);
			$curY += $summaryIntervalY;
		}
		$pdf->SetFont('Times','', 10);
		$totalAmountPayments = $totalAmount;
	}

	$curY += $summaryIntervalY;

	$totalDue = $totalAmountPledges - $totalAmountPayments;
	if ($totalDue > 0) {
		$curY += $summaryIntervalY;
		$dueString = sprintf ("Remaining pledge due: %.2f", ($totalAmountPledges - $totalAmountPayments));
		$pdf->WriteAt ($summaryDateX, $curY, $dueString);
		$curY += $summaryIntervalY;
	}

	$pdf->FinishPage ($curY);
}

if ($iPDFOutputType == 1) {
	$pdf->Output("ReminderReport" . date("Ymd") . ".pdf", true);
} else {
	$pdf->Output();
}	
?>
