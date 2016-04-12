<?php

function getApprovers($connection2) {
	try {
    	$data=array();
    	$sql="SELECT * FROM tripPlannerApprovers";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetchAll();
}

function getNameFromID($connection2, $gibbonPersonID) {
	try {
    	$data=array("gibbonPersonID" => $gibbonPersonID);
    	$sql="SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetch();
}

function getApprover($connection2, $tripPlannerApproverID) {
	try {
    	$data=array("tripPlannerApproverID" => $tripPlannerApproverID);
    	$sql="SELECT * FROM tripPlannerApprovers WHERE tripPlannerApproverID=:tripPlannerApproverID";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetch();
}

function approverExists($connection2, $tripPlannerApproverID) {
	$approver = getApprover($connection2, $tripPlannerApproverID);
	return (count($approver) > 0);
}

function isApprover($connection2, $gibbonPersonID) {
    try {
        $data=array("gibbonPersonID" => $gibbonPersonID);
        $sql="SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID";
        $result=$connection2->prepare($sql);
        $result->execute($data);
    }
    catch(PDOException $e) {
        print $e;
    }

    return ($result->rowCount())==1;
}

function needsApproval($connection2, $tripPlannerRequestID, $gibbonPersonID) {  
    if(isApprover($connection2, $gibbonPersonID)) {
        $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType") ;
         try {
            $data=array("tripPlannerRequestID" => $tripPlannerRequestID);
            $sql="SELECT * FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
            $result=$connection2->prepare($sql);
            $result->execute($data);
        }
        catch(PDOException $e) {
            return false;
        }
        $request = $result->fetch();
        if($requestApprovalType == "One Of") {
            return ($request["status"] == "Requested");
        }
        else if($requestApprovalType == "Two Of") {

        }
        else if($requestApprovalType == "Chain Of All") {

        }
    }
    return false;
}

function logEvent($connection2, $tripPlannerRequestID, $gibbonPersonID, $action, $comment = null) {
    if($tripPlannerRequestID != null && $gibbonPersonID != null && $action != null) {
         try {
            $data=array("tripPlannerRequestID" => $tripPlannerRequestID, "gibbonPersonID" => $gibbonPersonID, "action" => $action, "comment" => $comment);
            $sql="INSERT INTO tripPlannerRequestLog SET tripPlannerRequestID=:tripPlannerRequestID, gibbonPersonID=:gibbonPersonID, action=:action, comment=:comment";
            $result=$connection2->prepare($sql);
            $result->execute($data);
        }
        catch(PDOException $e) {
            
        }
    }
}

function makeCostBlock($guid, $connection2, $i, $mode="add", $outerBlock=TRUE) {
    if ($outerBlock) {
        print "<div id='blockOuter$i' class='blockOuter'>" ;
    }
    ?>
        <script type="text/javascript">
            console.log("starting");
            $(document).ready(function(){
                $("#blockInner<?php print $i ?>").css("display","none");
                $("#block<?php print $i ?>").css("height","72px")

                //Block contents control
                $('#show<?php print $i ?>').unbind('click').click(function() {
                    if ($("#blockInner<?php print $i ?>").is(":visible")) {
                        $("#blockInner<?php print $i ?>").css("display","none");
                        $("#block<?php print $i ?>").css("height","72px")
                        $('#show<?php print $i ?>').css("background-image", <?php print url("$_SESSION[$guid]['absoluteURL'] . '/themes/' . $_SESSION[$guid]['gibbonThemeName'] . '/img/plus.png")?>);
                    } else {
                        $("#blockInner<?php print $i ?>").slideDown("fast", $("#blockInner<?php print $i ?>").css("display","table-row"));
                        $("#block<?php print $i ?>").css("height","auto")
                        $('#show<?php print $i ?>').css("background-image", <?php print url("$_SESSION[$guid]['absoluteURL'] . '/themes/' . $_SESSION[$guid]['gibbonThemeName'] . '/img/minus.png")?>);
                    }
                });

                <?php if ($mode=="add") { ?>
                    var nameClick<?php print $i ?>=false ;
                    $('#name<?php print $i ?>').focus(function() {
                        if (nameClick<?php print $i ?>==false) {
                            $('#name<?php print $i ?>').css("color", "#000") ;
                            $('#name<?php print $i ?>').val("") ;
                            nameClick<?php print $i ?>=true ;
                        }
                    });

                    var costClick<?php print $i ?>=false ;
                    $('#cost<?php print $i ?>').focus(function() {
                        if (feeClick<?php print $i ?>==false) {
                            $('#cost<?php print $i ?>').css("color", "#000") ;
                            $('#cost<?php print $i ?>').val("") ;
                            feeClick<?php print $i ?>=true ;
                        }
                    });
                <?php } ?>

                $('#delete<?php print $i ?>').unbind('click').click(function() {
                    if (confirm("Are you sure you want to delete this record?")) {
                        $('#blockOuter<?php print $i ?>').fadeOut(600, function(){ $('#block<?php print $i ?>').remove(); });
                        cost<?php print $i ?>.destroy() ;
                    }
                });
            });
        </script>
        <div class='hiddenReveal' style='border: 1px solid #d8dcdf; margin: 0 0 5px' id="block<?php print $i ?>" style='padding: 0px'>
            <table class='blank' cellspacing='0' style='width: 100%'>
                <tr>
                    <td style='width: 70%'>
                        <input name='order[]' type='hidden' value='<?php print $i ?>'>
                        <input maxlength=100 id='name<?php print $i ?>' name='name<?php print $i ?>' type='text' style='float: none; border: 1px dotted #aaa; background: none; margin-left: 3px; <?php if ($mode=="add") { print "color: #999;" ;} ?> margin-top: 0px; font-size: 140%; font-weight: bold; width: 350px' value='<?php if ($mode=="add") { print __($guid, "Cost Name") . " $i" ;} else { print htmlPrep($name) ;} ?>'><br/>
                        <?php
                        if ($mode=="add") {
                            ?>
                            <select name="gibbonFinanceFeeCategoryID<?php print $i ?>" id="gibbonFinanceFeeCategoryID<?php print $i ?>" style='float: none; border: 1px dotted #aaa; background: none; margin-left: 3px; margin-top: 2px; font-size: 110%; font-style: italic; width: 250px'>
                                <?php
                                try {
                                    $dataSelect=array();
                                    $sqlSelect="SELECT * FROM gibbonFinanceFeeCategory WHERE active='Y' AND NOT gibbonFinanceFeeCategoryID=1 ORDER BY name" ;
                                    $resultSelect=$connection2->prepare($sqlSelect);
                                    $resultSelect->execute($dataSelect);
                                }
                                catch(PDOException $e) { }
                                while ($rowSelect=$resultSelect->fetch()) {
                                    print "<option value='" . $rowSelect["gibbonFinanceFeeCategoryID"] . "'>" . $rowSelect["name"] . "</option>" ;
                                }
                                print "<option selected value='1'>" . __($guid, 'Other') . "</option>" ;
                                ?>
                            </select>
                            <?php
                        }
                        ?>
                        <input maxlength=13 id='cost<?php print $i ?>' name='cost<?php print $i ?>' type='text' style='float: none; border: 1px dotted #aaa; background: none; margin-left: 3px; <?php if ($mode=="add") { print "color: #999;" ;} ?> margin-top: 2px; font-size: 110%; font-style: italic; width: 95px' value='<?php if ($mode=="add") { print __($guid, "Value") ; if ($_SESSION[$guid]["currency"]!="") { print " (" . $_SESSION[$guid]["currency"] . ")" ;}} else { print htmlPrep($cost) ;} ?>'>
                        <script type="text/javascript">
                            var cost<?php print $i ?>=new LiveValidation('cost<?php print $i ?>');
                            cost<?php print $i ?>.add(Validate.Presence);
                            cost<?php print $i ?>.add( Validate.Format, { pattern: /^(?:\d*\.\d{1,2}|\d+)$/, failureMessage: "Invalid number format!" } );
                        </script>
                    </td>
                    <td style='text-align: right; width: 30%'>
                        <div style='margin-bottom: 5px'>
                            <?php
                            print "<img id='delete$i' title='" . __($guid, 'Delete') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/garbage.png'/> " ;
                            print "<div id='show$i'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -1px; margin-left: 3px; padding-right: 1px; float: right; width: 25px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png\")'></div></br>" ;
                            ?>
                        </div>
                        <?php
                        if ($mode=="plannerEdit") {
                            print "</br>" ;
                        }
                        ?>
                    </td>
                </tr>
                <tr id="blockInner<?php print $i ?>">
                    <td colspan=2 style='vertical-align: top'>
                        <?php
                        print "<div style='text-align: left; font-weight: bold; margin-top: 15px'>Description</div>" ;
                        print "<textarea style='width: 100%;' name='description" . $i . "'>" . htmlPrep("") . "</textarea>" ;
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    <?php
    if ($outerBlock) {
        print "</div>" ;
    }
}

?>