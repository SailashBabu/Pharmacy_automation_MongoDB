<?php
session_start();
include('connect.php');

// Check if the cart is empty and redirect if necessary
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: billing.php");
    exit;
}

// Fetch cart items from session
$cart_items = $_SESSION['cart'];

// Handle form submission for finalizing the bill
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the empty cart button was clicked
    if (isset($_POST['empty_cart'])) {
        // Clear the cart
        $_SESSION['cart'] = [];
        echo "<script>alert('Cart has been emptied.');</script>";
        header("Location: billing.php"); // Redirect back to billing page after emptying the cart
        exit;
    }
    if (isset($_POST['finalize_bill'])) {
        // Check if customer name and mobile number are provided
        if (empty($_POST['customer_name']) || empty($_POST['customer_mobile'])) {
            echo "<script>alert('Please fill in customer name and mobile number to finalize the bill.');</script>";
            // Do not proceed with finalizing the bill, just return to the form
        } else {

            $datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

            // Get the ISO 8601 format string
            $ist_timestamp = $datetime->format('Y-m-d H:i:s');
    // Create a BulkWrite object for updating inventory
    $bulk = new MongoDB\Driver\BulkWrite;
            $customer_name=$_POST['customer_name'];
            $customer_number=$_POST['customer_mobile'];
            $doctor=$_POST['doctor_name'];
            $age=$_POST['age'];
            $gender=$_POST['gender'];
    // Prepare transaction details
    $transaction_details = [
        'customer_name' => $customer_name,
        'customer_mobile' => $customer_number,
        'doctor_name' => $doctor,
        'age' => $age,
        'gender' => $gender,
        'items' => [],
        'total_amount' => 0,
        'timestamp' => $ist_timestamp
    ];

    foreach ($cart_items as $item) {
        // Calculate total amount for this item
        $total_price = $item['quantity'] * $item['price'];
        $transaction_details['total_amount'] += $total_price;

        // Prepare update for inventory
        $filter = ['Name' => $item['name']];
        $update = ['$inc' => ['Quantity' => -$item['quantity']]]; // Decrease quantity

        // Add item to transaction details
        $transaction_details['items'][] = [
            'name' => $item['name'],
            'batch_no' => $item['batch_no'],
            'expiry_date' => $item['expiry_date'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'total_price' => $total_price
        ];

        // Add update operation to bulk write
        $bulk->update($filter, $update);
    }

    // Execute bulk write to update inventory
    executeBulkWrite('medicines', $bulk);

    // Check if any medicine's quantity is now zero and delete it
    foreach ($cart_items as $item) {
        // Check if quantity is now zero after decrementing
        $medicine_filter = ['Name' => $item['name']];
        $updated_medicine = executeQuery('medicines', $medicine_filter);
        
        if (!empty($updated_medicine) && $updated_medicine[0]->Quantity <= 0) {
            // Create a BulkWrite object for deleting medicine
            $delete_bulk = new MongoDB\Driver\BulkWrite;
            $delete_bulk->delete($medicine_filter);
            executeBulkWrite('medicines', $delete_bulk);
        }
    }

    // Insert transaction details into transactions collection
    $bulk_transaction = new MongoDB\Driver\BulkWrite;
    $bulk_transaction->insert($transaction_details);
    executeBulkWrite('transactions', $bulk_transaction);

    // Clear the cart after processing the transaction
    $_SESSION['cart'] = [];
    $_SESSION['transaction_details'] = $transaction_details;
    // Redirect to index page after successful transaction
    // echo "<script>alert('Transaction complete.'); window.location.href='index.php';</script>";
    header("Location: bill-format.php");
    exit;
}
}
}
if (isset($_GET['remove_item'])) {
    unset($_SESSION['cart'][$_GET['remove_item']]); // Remove item by index in cart array

    // Re-indexing the cart array to maintain sequential keys after removal.
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
    require "navbar.php";
    ?>
    <div class="container">
        <h1>Your Cart</h1>
        <form method='post'>
            <div class='input-row'>
                <div class='input-group'>
                    <label for='customer_name'>Name:</label>
                    <input type='text' id='customer_name' name='customer_name' placeholder='Enter your name'>
                </div>

                <div class='input-group'>
                    <label for='customer_mobile'>Mobile:</label>
                    <input type='text' id='customer_mobile' name='customer_mobile' placeholder='Enter your mobile number'>
                </div>
                <div class='input-group'>
                    <label for='doctor_name'>Dr Name:</label>
                    <input type='text' id='doctor_name' name='doctor_name' placeholder='Enter Doctor Name'>
                </div>
                <div class='input-group'>
                    <label for='Age'>Age:</label>
                    <input type='number' id='age' name='age' placeholder='Enter Age'>
                </div>
                <div class='input-group'>
                    <label for='gender'>Gender:</label>
                    <input type='text' id='gender' name='gender' placeholder='Enter Gender'>
                </div>

            </div>

        <!-- Cart Summary Table -->
        <table border='1'>
            <thead>
                <tr style='background-color: #4CAF50; color: white;'>
                    <th>Name</th><th>Batch No</th><th>Expiry Date</th><th>Quantity Selected</th><th>MRP</th><th>Total Price</th></tr></thead><tbody><?php 
                    foreach ($cart_items as $index => $item): 

                        echo "<tr><td>" . htmlspecialchars($item['name']) . "</td><td>" . htmlspecialchars($item['batch_no']) . "</td><td>" . htmlspecialchars($item['expiry_date']) . "</td><td>" . htmlspecialchars($item['quantity']) . "</td><td>" . htmlspecialchars($item['price']) . "</td><td>" . htmlspecialchars($item['quantity'] * $item['price']) . "</td>";
                        echo "<td><a href='cart.php?remove_item=" . htmlspecialchars($index) . "' class='button delete'>Remove</a></td></tr>"; 
                          endforeach; 
                    ?>
                </tbody></table>

                <!-- Total Price Calculation -->
                <?php 
                $grandTotal = 0;
                foreach ($cart_items as $item) {
                    $grandTotal += $item['quantity'] * $item['price'];
                }
                ?>
                
                Total Amount: <?= htmlspecialchars($grandTotal); ?><br/><br/>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type='submit' name='finalize_bill' class='button'>Finalize Bill</button>
                    <button type='submit' name='empty_cart' class='button delete'>Empty Cart</button>
                    <a href='billing.php' class='button'>Back to Billing</a>
                </div>

            </div>
            </form>
        </body>
            
        </html>