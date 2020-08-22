<?php

    define("CONNECT_DB", "includes/transactionConfig.php");

    class Transactions{

        private $con;
        private $errorArray;

        
        

        public function __construct($con) {
            $this->con = $con;
            $this->errorArray = array();
            $this->SuccessArray = array();
        }

        #   Funtion to send Credits to an intended user account

        public function sendcredits($sen, $reciv, $amt) {

            $query = mysqli_query($this->con, "SELECT * FROM user_details WHERE email_id='$reciv'");
            $creditbalance = mysqli_query($this->con, "SELECT credits FROM user_details WHERE email_id='$sen'");
            $resultarr = mysqli_fetch_assoc($creditbalance);
            
            if($amt < 1) { 
                array_push($this->errorArray, Constants::$amountLessthanOne);
            }
            else if($resultarr['credits'] < $amt) { 
                array_push($this->errorArray, Constants::$InsufficientBalance);
            }
            else if(mysqli_num_rows($query) != 1) {
                array_push($this->errorArray, Constants::$usernameInvalid);
            }
            else if($sen == $reciv) {
                array_push($this->errorArray, Constants::$cantSendSelf);
            }
            else { 
                $this->sendCreditToUser($sen, $reciv, $amt);
                return true;
            }
        }

        #   Funtion to send Credits to an intended user account

        public function sendRequestedcredits($sen, $reciv, $amt) {

            $query = mysqli_query($this->con, "SELECT * FROM user_details WHERE email_id='$reciv'");

            $creditbalance = mysqli_query($this->con, "SELECT credits FROM user_details WHERE email_id='$sen'");
            $resultarr = mysqli_fetch_assoc($creditbalance);
            
            if($amt < 1) { 
                array_push($this->errorArray, Constants::$amountLessthanOne);
            }
            else if($resultarr['credits'] < $amt) { 
                array_push($this->errorArray, Constants::$InsufficientBalanceForReq);
            }
            else if(mysqli_num_rows($query) != 1) {
                array_push($this->errorArray, Constants::$usernameInvalid);
            }
            else if($sen == $reciv) {
                array_push($this->errorArray, Constants::$cantSendSelf);
            }
            else { 
                $this->sendCreditToUser($sen, $reciv, $amt);
                return true;
            }
            
        }
        
        #   Funtion to request Credits from an intended user account

        public function reqCredits($sen, $reciv, $amt) {

            $query = mysqli_query($this->con, "SELECT * FROM user_details WHERE email_id='$reciv'");
            
            if($amt < 1) { 
                array_push($this->errorArray, Constants::$amountLessthanOne);
            }
            else if(mysqli_num_rows($query) != 1) {
                array_push($this->errorArray, Constants::$usernameInvalid);
            }
            else if($sen == $reciv) {
                array_push($this->errorArray, Constants::$cantReqSelf);
            }
            else { 
                $this->receiveCreditFromUser($sen, $reciv, $amt);
                return true;
            }
            
        }

        #   Function to generate voucher ID and store it in a database

        public function generateVoucherID($sen, $amt) {
            
            $VoucherID = $this->generateRandomString(8);

            $creditbalance = mysqli_query($this->con, "SELECT credits FROM user_details WHERE email_id='$sen'");
            $resultarr = mysqli_fetch_assoc($creditbalance);
            
            # Create and check a new connection to the database

            include(CONNECT_DB);
            
            $sen = $this->getUserId($sen);

            if($amt < 1) { 
                array_push($this->errorArray, Constants::$amountLessthanOne);
                return false;
            }
            else if($resultarr['credits'] < $amt) { 
                array_push($this->errorArray, Constants::$InsufficientBalanceForReq);
                return false;
            }else{
                try {  
                    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    # begin a Transaction
                    $dbh->beginTransaction();

                    # A set of queries; if one fails, an exception should be thrown
                    $sth = $dbh->prepare("INSERT INTO `voucher_table`(`sender_id`, `voucher_amount`, `voucher_code`) VALUES (?,?,?)");
                    $sth->execute(array($sen,$amt,$VoucherID));
                    $sth = $dbh->prepare("UPDATE `user_details` SET `credits`=`credits`-? WHERE `user_ID` =?");
                    $sth->execute(array($amt, $sen));
                    # If we arrive here, it means that no exception was thrown
                    # i.e. no query has failed, and we can commit the transaction
                    $dbh->commit();
                
                } catch (Exception $e) {
                    # An exception has been thrown; We must rollback the transaction
                    $dbh->rollBack();
                    array_push($this->errorArray, Constants::$TranscErrSend);
                }
                # closing connection 
                $dbh = null;
                $sth = null;
            }

            return "<span class='voucherIdhere'>Voucher ID: $VoucherID</span>";

        }

        #   Function to Redeem from a voucher ID and drop it from the database

        public function redeemVoucherID($sen, $vId) {

            $sen = $this->getUserId($sen);

            $checkVoucherCodeQuery = mysqli_query($this->con, "SELECT `voucher_id` FROM `voucher_table` WHERE `voucher_code`='$vId'");

            #   Fetches amount that is need to be added if redeemed
            $amt = mysqli_query($this->con, "SELECT `voucher_amount` FROM `voucher_table` WHERE `voucher_code`='$vId'");
            $amt = mysqli_fetch_array($amt);
            $amt = isset($amt['voucher_amount']) ? ($amt['voucher_amount']) : 0;

            if (mysqli_num_rows($checkVoucherCodeQuery) == 0) {
                array_push($this->errorArray, Constants::$voucherCodeInvalid);
            } else {

                # Create and check a new connection to the database
                include(CONNECT_DB);

                try {  
                    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
                    # begin a Transaction
                    $dbh->beginTransaction();
    
                    # A set of queries; if one fails, an exception should be thrown
                    $sth = $dbh->prepare("UPDATE `user_details` SET `credits`=`credits`+? WHERE `user_ID` =?");
                    $sth->execute(array($amt,$sen));
                    $sth = $dbh->prepare("DELETE FROM `voucher_table` WHERE  `voucher_code` = ?");
                    $sth->execute(array($vId));
                    # If we arrive here, it means that no exception was thrown
                    # i.e. no query has failed, and we can commit the transaction
                    array_push($this->SuccessArray, Constants::$RequestSent);
                    $dbh->commit();
                    array_push($this->SuccessArray, Constants::$VoucherRedeemed);

                } catch (Exception $e) {
                    # An exception has been thrown; We must rollback the transaction
                    $dbh->rollBack();
                    array_push($this->errorArray, Constants::$TranscErr);
                }
                # closing connection 
                $dbh = null;
                $sth = null;
                return true;
            }

            

        }

        #   Function to delete column using Row ID

        public function deleteRowWithID($Id) {

            include(CONNECT_DB);

            try {  
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                # begin a Transaction
                $dbh->beginTransaction();

                # A set of queries; if one fails, an exception should be thrown

                $sth = $dbh->prepare("DELETE FROM `credit_requests` WHERE  `req_id` = ?");
                $sth->execute(array($Id));

                # If we arrive here, it means that no exception was thrown
                # i.e. no query has failed, and we can commit the transaction
                $dbh->commit();
                
            } catch (Exception $e) {
                # An exception has been thrown; We must rollback the transaction
                $dbh->rollBack();
                array_push($this->errorArray, Constants::$TranscErr);
            }
            # closing connection 
            $dbh = null;
            $sth = null;
        }

        #   Function to get USER_ID from the Email-ID

        public function getUserId($un) {
            include(CONNECT_DB);
            
            try {  
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                # begin a Transaction
                $dbh->beginTransaction();

                # A set of queries; if one fails, an exception should be thrown
                $sth = $dbh->prepare("SELECT `user_ID` FROM `user_details` WHERE email_id=?");
                $sth->execute(array($un));
                
                # If we arrive here, it means that no exception was thrown
                # i.e. no query has failed, and we can commit the transaction
                $UserID = $sth -> fetch();
                $UserID = $UserID["user_ID"];
                array_push($this->SuccessArray, Constants::$RequestSent);
                $dbh->commit();
                
            } catch (Exception $e) {
                # An exception has been thrown; We must rollback the transaction
                $dbh->rollBack();
                array_push($this->errorArray, Constants::$TranscErr);
            } finally {
                # closing connection 
                $dbh = null;
                $sth = null;
            }

            return $UserID;

        }

        #   Getting the error array ready

        Public function getError($error) {
            if(!in_array($error, $this->errorArray)) {
                $error = "";
            }
            return "<span class='errorMessage'>$error</span>";
        }

        #   Getting the Success array ready
        Public function getSuccess($Success) {
            if(!in_array($Success, $this->SuccessArray)) {
                $Success = "";
            }
            return "<span class='successMessage'>$Success</span>";
        }

        #   Function to generate random strings

        private function generateRandomString($length = 10) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }

        #   Function to send MySQL commands for sending Credits

        private function sendCreditToUser($sen, $reciv, $amt) {
            
            # Create and check a new connection to the database
            include(CONNECT_DB);

            try {  
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                # begin a Transaction
                $dbh->beginTransaction();
                $date = date("Y-m-d h:i:sa");
                # A set of queries; if one fails, an exception should be thrown
                $sth = $dbh->prepare("UPDATE `user_details` SET `credits`=`credits`-? WHERE email_id=?");
                $sth->execute(array($amt,$sen));
                $sth = $dbh->prepare("UPDATE `user_details` SET `credits`=`credits`+? WHERE email_id=?");
                $sth->execute(array($amt,$reciv));

                $sen = $this->getUserId($sen);
                $reciv = $this->getUserId($reciv);

                #   Saves transation information to transaction table
                $sth = $dbh->prepare("INSERT INTO `transaction_table`(`sender_id`, `receiver_id`, `transaction_date`, `transaction_amount`) VALUES (?,?,?,?)");
                $sth->execute(array($sen,$reciv,$date,$amt));
                
                # If we arrive here, it means that no exception was thrown
                array_push($this->SuccessArray, Constants::$CreditsSent);
                
                # i.e. no query has failed, and we can commit the transaction
                $dbh->commit();
                
            } catch (Exception $e) {
                # An exception has been thrown; We must rollback the transaction
                $dbh->rollBack();
                array_push($this->errorArray, Constants::$TranscErrSend);
                return false;
            } finally {
                # closing connection 
                $dbh = null;
                $sth = null;
            }
            
        }

        #   Function to send MySQL commands for pushing req info to database

        private function receiveCreditFromUser($sen, $reciv, $amt) {

            $date = date("Y-m-d h:i:sa");
            # Create and check a new connection to the database
            include(CONNECT_DB);
            $sen = $this->getUserId($sen);
            $reciv = $this->getUserId($reciv);

            try {  
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                # begin a Transaction
                $dbh->beginTransaction();

                # A set of queries; if one fails, an exception should be thrown
                $sth = $dbh->prepare("INSERT INTO `credit_requests`(`req_from`, `send_from`, `credits_requested`, `req_dateTime`) VALUES (?,?,?,?)");
                $sth->execute(array($sen,$reciv,$amt,$date));
                
                # If we arrive here, it means that no exception was thrown
                # i.e. no query has failed, and we can commit the transaction
                array_push($this->SuccessArray, Constants::$RequestSent);
                $dbh->commit();
                
            } catch (Exception $e) {
                # An exception has been thrown; We must rollback the transaction
                $dbh->rollBack();
                array_push($this->errorArray, Constants::$TranscErr);
            }
            
            # closing connection 
            $dbh = null;
            $sth = null;
        }

    }

?>