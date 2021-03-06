<?php

require '../Include/Config.php';
require '../Include/Functions.php';

//Security
if (!isset($_SESSION['iUserID'])) {
  Redirect("Login.php");
  exit;
}

// Services
require_once "../Service/PersonService.php";
require_once "../Service/FamilyService.php";
require_once "../Service/DataSeedService.php";
require_once "../Service/FinancialService.php";
require_once "../Service/GroupService.php";
require_once '../Service/SystemService.php';
require_once "../Service/ReportingService.php";
require_once '../Service/NoteService.php';

require_once '../vendor/Slim/slim/Slim/Slim.php';

use Slim\Slim;

Slim::registerAutoloader();

$app = new Slim();
$app->config('debug', false);

$app->contentType('application/json');

$app->error(function(Exception $e) use ($app) {
  $app->response->setStatus($e->getCode());
  echo json_encode(["error" => ["text" => $e->getMessage()]]);
});

$app->container->singleton('PersonService', function () {
  return new PersonService();
});

$app->container->singleton('FamilyService', function () {
  return new FamilyService();
});

$app->container->singleton('DataSeedService', function () {
  return new DataSeedService();
});
$app->container->singleton('SystemService', function () {
  return new SystemService();
});

$app->container->singleton('FinancialService', function () {
  return new FinancialService();
});

$app->container->singleton('GroupService', function () {
  return new GroupService();
});

$app->container->singleton('NoteService', function () {
  return new NoteService();
});

$app->container->singleton('ReportingService', function () {
  return new ReportingService();
});

$app->group('/groups', function () use ($app) {
  $groupService = $app->GroupService;

  $app->post('/:groupID/userRole/:userID', function ($groupID, $userID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo json_encode($groupService->setGroupMemberRole($groupID, $userID, $input->roleID));
  });

  $app->post('/:groupID/removeuser/:userID', function ($groupID, $userID) use ($groupService) {

    $groupService->removeUserFromGroup($groupID, $userID);
    echo json_encode(["success" => true]);
  });
  $app->post('/:groupID/adduser/:userID', function ($groupID, $userID) use ($groupService) {

    echo json_encode($groupService->addUserToGroup($groupID, $userID, 0));
  });
  $app->delete('/:groupID', function ($groupID) use ($groupService) {

    $groupService->deleteGroup($groupID);
    echo json_encode(["success" => true]);
  });

  $app->get('/:groupID', function ($groupID) use ($groupService) {

    echo $groupService->getGroupJSON($groupService->getGroups($groupID));
  });

  $app->post('/:groupID', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo $groupService->updateGroup($groupID, $input);
  });

  $app->post('/', function () use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo json_encode($groupService->createGroup($input->groupName));
  });

  $app->post('/:groupID/roles/:roleID', function ($groupID, $roleID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    if (property_exists($input, "groupRoleName")) {
      $groupService->setGroupRoleName($groupID, $roleID, $input->groupRoleName);
    }
    elseif (property_exists($input, "groupRoleOrder")) {
      $groupService->setGroupRoleOrder($groupID, $roleID, $input->groupRoleOrder);
    }

    echo json_encode(["success" => true]);
  });

  $app->delete('/:groupID/roles/:roleID', function ($groupID, $roleID) use ($app, $groupService) {

    echo json_encode($groupService->deleteGroupRole($groupID, $roleID));
  });

  $app->post('/:groupID/roles', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo $groupService->addGroupRole($groupID, $input->roleName);
  });

  $app->post('/:groupID/defaultRole', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    $groupService->setGroupRoleAsDefault($groupID, $input->roleID);
    echo json_encode(["success" => true]);
  });

  $app->post('/:groupID/setGroupSpecificPropertyStatus', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    if ($input->GroupSpecificPropertyStatus) {
      $groupService->enableGroupSpecificProperties($groupID);
      echo json_encode(["status" => "group specific properties enabled"]);
    }
    else {
      $groupService->disableGroupSpecificProperties($groupID);
      echo json_encode(["status" => "group specific properties disabled"]);
    }
  });
});

$app->group('/queries', function () use ($app) {
  $reportingService = $app->ReportingService;

  $app->get("/", function () use ($app, $reportingService) {
    echo $reportingService->getQueriesJSON($reportingService->getQuery());
  });

  $app->get("/:id", function ($id) use ($app, $reportingService) {
    echo $reportingService->getQueriesJSON($reportingService->getQuery($id));
  });

  $app->get("/:id/details", function ($id) use ($app, $reportingService) {
    echo json_encode(["Query" => $reportingService->getQuery($id),"Parameters" => $reportingService->getQueryParameters($id)]);
  });

  $app->post('/:id', function() use ($app, $reportingService) {
    $input = getJSONFromApp($app);
    echo json_encode($reportingService->queryDatabase($input));
  });
});

$app->group('/database', function () use ($app) {
  $systemService = $app->SystemService;
  $app->post('/backup', function () use ($app, $systemService) {
    $input = getJSONFromApp($app);
    $backup = $systemService->getDatabaseBackup($input);
    echo json_encode($backup);
  });

  $app->post('/restore', function () use ($app, $systemService) {

    $request = $app->request();
    $body = $request->getBody();
    $restore = $systemService->restoreDatabaseFromBackup();
    echo json_encode($restore);
  });

  $app->get('/download/:filename', function ($filename) use ($app, $systemService) {

    $systemService->download($filename);
  });
});

$app->group('/search', function () use ($app) {
  $app->get('/:query', function ($query) use ($app) {

    $resultsArray = array();
    try { array_push($resultsArray, $app->PersonService->getPersonsJSON($app->PersonService->search($query))); }
    catch (Exception $e) {
      
    }
    try { array_push($resultsArray, $app->FamilyService->getFamiliesJSON($app->FamilyService->search($query))); }
    catch (Exception $e) {
      
    }
    try { array_push($resultsArray, $app->GroupService->getGroupJSON($app->GroupService->search($query))); }
    catch (Exception $e) {
      
    }
    try { array_push($resultsArray, $app->FinancialService->getDepositJSON($app->FinancialService->searchDeposits($query))); }
    catch (Exception $e) {
      
    }
    try { array_push($resultsArray, $app->FinancialService->getPaymentJSON($app->FinancialService->searchPayments($query))); }
    catch (Exception $e) {
      
    }
  echo json_encode(["results" => array_filter($resultsArray)]);
  });
});


$app->group('/persons', function () use ($app) {
  $personService = $app->PersonService;
  $app->get('/search/:query', function ($query) use ($personService) {

    echo "[" . $personService->getPersonsJSON($personService->search($query)) . "]";
  });

  $app->group('/:id', function () use ($app, $personService) {
    $app->get('/', function ($id) use ($personService) {
      echo "[" . $personService->getPersonsJSON($personService->getPersonByID($id)) . "]";
    });

    $app->get('/photo', function ($id) use ($personService) {

      echo $personService->getPhoto($id);
    });
    $app->delete('/photo', function ($id) use ($personService) {

      $deleted = $personService->deleteUploadedPhoto($id);
      if (!$deleted)
        echo json_encode(["filesDeleted" => "no images found"]);
      else
        echo json_encode(["filesDeleted" => true]);
    });
  });
});

$app->group('/families', function () use ($app) {
  $app->get('/search/:query', function ($query) use ($app) {

    echo $app->FamilyService->getFamiliesJSON($app->FamilyService->search($query));
  });
  $app->get('/lastedited', function ($query) use ($app) {

    $app->FamilyService->lastEdited();
  });
  $app->get('/byCheckNumber/:tScanString', function ($tScanString) use ($app) {

    echo $app->FinancialService->getMemberByScanString($sstrnig);
  });
  $app->get('/byEnvelopeNumber/:tEnvelopeNumber', function ($tEnvelopeNumber) use ($app) {

    echo $app->FamilyService->getFamilyStringByEnvelope($tEnvelopeNumber);
  });
});

$app->group('/deposits', function () use ($app) {

  $app->post('/', function () use ($app) {

    $input = getJSONFromApp($app);
    echo json_encode($app->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate));
  });

  $app->get('/', function () use ($app) {

    echo json_encode(["deposits" => $app->FinancialService->getDeposits()]);
  });

  $app->get('/:id', function ($id) use ($app) {

    echo json_encode(["deposits" => $app->FinancialService->getDeposits($id)]);
  })->conditions(array('id' => '[0-9]+'));

  $app->post('/:id', function ($id) use ($app) {

    $input = getJSONFromApp($app);
    echo json_encode($app->FinancialService->setDeposit($input->depositType, $input->depositComment, $input->depositDate, $id, $input->depositClosed));
  });


  $app->get('/:id/ofx', function ($id) use ($app) {

    $OFX = $app->FinancialService->getDepositOFX($id);
    header($OFX->header);
    echo $OFX->content;
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/pdf', function ($id) use ($app) {

    $PDF = $app->FinancialService->getDepositPDF($id);
    header($PDF->header);
    echo $PDF->content;
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/csv', function ($id) use ($app) {

    $CSV = $app->FinancialService->getDepositCSV($id);
    header($CSV->header);
    echo $CSV->content;
  })->conditions(array('id' => '[0-9]+'));

  $app->delete('/:id', function ($id) use ($app) {

    $app->FinancialService->deleteDeposit($id);
    echo json_encode(["success" => true]);
  })->conditions(array('id' => '[0-9]+'));

  $app->get('/:id/payments', function ($id) use ($app) {

    echo $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments($id));
  })->conditions(array('id' => '[0-9]+'));
});


$app->group('/payments', function () use ($app) {
  $app->get('/', function () use ($app) {

    $app->FinancialService->getPaymentJSON($app->FinancialService->getPayments());
  });
  $app->post('/', function () use ($app) {

    $payment = getJSONFromApp($app);
    echo json_encode(["payment" => $app->FinancialService->submitPledgeOrPayment($payment)]);
  });
  $app->get('/:id', function ($id) use ($app) {

//$payment = getJSONFromApp($app);
//echo $app->FinancialService->getDepositsByFamilyID($fid); //This might not work yet...
    echo json_encode(["status" => "Not Implemented"]);
  });
  $app->get('/byFamily/:familyId(/:fyid)', function ($familyId, $fyid = -1) use ($app) {

    echo '{"status":"Not implemented"}';
//$payment = getJSONFromApp($app);
#$app->FinancialService->getDepositsByFamilyID($fid);//This might not work yet...
  });
  $app->delete('/:groupKey', function ($groupKey) use ($app) {

    $app->FinancialService->deletePayment($groupKey);
    echo json_encode(["status" => "ok"]);
  });
});

$app->group('/notes', function () use ($app) {
  $noteService = $app->NoteService;

  $app->delete('/:noteID', function ($noteID) use ($noteService) {

    $noteService->deleteNoteById($noteID);
    echo json_encode(["success" => true]);
  });

  $app->get('/:noteId', function ($noteId) use ($noteService) {

    echo json_encode($noteService->getNoteById($noteId));
  });
});

$app->group('/data/seed', function () use ($app) {
  $app->post('/families', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $families = $input->families;
    $app->DataSeedService->generateFamilies($families);
  });
  $app->post('/sundaySchoolClasses', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $classes = $input->classes;
    $childrenPerTeacher = $input->childrenPerTeacher;
    $app->DataSeedService->generateSundaySchoolClasses($classes, $childrenPerTeacher);
  });
  $app->post('/deposits', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $deposits = $input->deposits;
    $averagedepositvalue = $input->averagedepositvalue;
    $app->DataSeedService->generateDeposits($deposits, $averagedepositvalue);
  });
  $app->post('/events', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $events = $input->events;
    $averageAttendance = $input->averageAttendance;
    $app->DataSeedService->generateEvents($events, $averageAttendance);
  });
  $app->post('/fundraisers', function () use ($app) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $fundraisers = $input->fundraisers;
    $averageItems = $input->averageItems;
    $averageItemPrice = $input->averageItemPrice;
    $app->DataSeedService->generateFundRaisers($fundraisers, $averageItems, $averageItemPrice);
  });
});

$app->group('/issues', function () use ($app)
{
  $systemService = $app->SystemService;
  $app->post('/', function () use ($app, $systemService) {
        $request = $app->request();
        $body = $request->getBody();
        $input = json_decode($body);
        $app->SystemService->reportIssue($input);
  });
  
});

function getJSONFromApp($app)
{
	$request = $app->request();
  $body = $request->getBody();
  return json_decode($body);
}



$app->run();

