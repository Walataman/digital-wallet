<?php include("includes/header.php"); ?>

<!--    Send Money Handler  -->

<?php

include("includes/classes/Transactions.php"); 

$transactions = new Transactions($con);

include("includes/handlers/transaction-handler.php"); 

?>


<!--    Website Code    -->

<!--    Request Money Form    -->
<div class="row justify-content-center my-5">
    <div class="col-6">
        <form class="reqCreditMoney" action="" method="POST">
            <div class="form-group ">
                <label for="reqFrom">Request From</label>
                <input type="text" class="form-control" id="reqFrom" name="reqFrom" placeholder="Enter Email...">
                <?php echo $transactions->getError(Constants::$usernameInvalid); ?>
                <?php echo $transactions->getError(Constants::$cantReqSelf); ?>
            </div>
            <div class="form-group">
                <label for="reqAmount">Amount</label>
                <input type="number" class="form-control" id="reqAmount" name="reqAmount" placeholder="Enter Amount...">
                <?php echo $transactions->getError(Constants::$InsufficientBalance); ?>
                <?php echo $transactions->getError(Constants::$amountLessthanOne); ?>
            </div>
            <div class="form-group">
                <button type="submit" name="reqMoneyButton" class="btn btn-primary">Request</button>
            </div>
        </form>
        <?php echo $transactions->getSuccess(Constants::$RequestSent); ?>
        <?php echo $transactions->getError(Constants::$TranscErr); ?>
    </div>
</div>
<!--    Request Money Form End   -->

<!--    Stops form from resubmitting    -->
<script>
    
if ( window.history.replaceState ) {
  window.history.replaceState( null, null, window.location.href );
}

</script>
<!--    Stops form from resubmitting    -->
<?php include("includes/footer.php"); ?>