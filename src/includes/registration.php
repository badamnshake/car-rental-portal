<?php
//error_reporting(0);
if(isset($_POST['signup']))
{
$fname=$_POST['fullname'];
$email=$_POST['emailid']; 
$mobile=$_POST['mobileno'];
$dobInput=$_POST['dob']; // DD/MM/YYYY format from form
$password=md5($_POST['password']); 

// Convert DD/MM/YYYY to YYYY-MM-DD for database storage
$dobFormatted = null;
if (!empty($dobInput)) {
    $dateParts = explode('/', $dobInput);
    if (count($dateParts) === 3) {
        $day = $dateParts[0];
        $month = $dateParts[1];
        $year = $dateParts[2];
        
        // Validate and format to YYYY-MM-DD
        if (checkdate($month, $day, $year)) {
            $dobFormatted = sprintf('%04d-%02d-%02d', $year, $month, $day);
        } else {
            echo "<script>alert('Invalid date format. Please use DD/MM/YYYY');</script>";
            exit();
        }
    } else {
        echo "<script>alert('Invalid date format. Please use DD/MM/YYYY');</script>";
        exit();
    }
}

$sql="INSERT INTO tblusers(FullName,EmailId,ContactNo,dob,Password) VALUES(:fname,:email,:mobile,:dob,:password)";
$query = $dbh->prepare($sql);
$query->bindParam(':fname',$fname,PDO::PARAM_STR);
$query->bindParam(':email',$email,PDO::PARAM_STR);
$query->bindParam(':mobile',$mobile,PDO::PARAM_STR);
$query->bindParam(':dob',$dobFormatted,PDO::PARAM_STR);
$query->bindParam(':password',$password,PDO::PARAM_STR);
$query->execute();
$lastInsertId = $dbh->lastInsertId();
if($lastInsertId)
{
echo "<script>alert('Registration successful. Now you can login');</script>";
}
else 
{
echo "<script>alert('Something went wrong. Please try again');</script>";
}
}
?>

<script>
function checkAvailability() {
$("#loaderIcon").show();
jQuery.ajax({
url: "check_availability.php",
data:'emailid='+$("#emailid").val(),
type: "POST",
success:function(data){
$("#user-availability-status").html(data);
$("#loaderIcon").hide();
},
error:function (){}
});
}

// Date of birth validation and formatting
function validateAge() {
    const dobInput = document.getElementById('dob');
    const dobValue = dobInput.value.trim();
    
    if (!dobValue) return true; // Let HTML5 required handle empty field
    
    // Validate DD/MM/YYYY format
    const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
    const match = dobValue.match(dateRegex);
    
    if (!match) {
        alert('Please enter date in DD/MM/YYYY format (e.g., 15/03/1990)');
        dobInput.focus();
        return false;
    }
    
    const day = parseInt(match[1]);
    const month = parseInt(match[2]);
    const year = parseInt(match[3]);
    
    // Validate ranges
    if (day < 1 || day > 31) {
        alert('Please enter a valid day (01-31)');
        dobInput.focus();
        return false;
    }
    
    if (month < 1 || month > 12) {
        alert('Please enter a valid month (01-12)');
        dobInput.focus();
        return false;
    }
    
    if (year < 1900 || year > new Date().getFullYear()) {
        alert('Please enter a valid year');
        dobInput.focus();
        return false;
    }
    
    // Create date object and validate it's a real date
    const birthDate = new Date(year, month - 1, day);
    if (birthDate.getDate() !== day || birthDate.getMonth() !== (month - 1) || birthDate.getFullYear() !== year) {
        alert('Please enter a valid date (e.g., 29/02 only exists in leap years)');
        dobInput.focus();
        return false;
    }
    
    // Check age
    const today = new Date();
    const age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    const actualAge = (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) 
                      ? age - 1 : age;
    
    if (actualAge < 18) {
        alert('You must be at least 18 years old to register.');
        dobInput.focus();
        return false;
    }
    
    if (actualAge > 100) {
        alert('Please enter a valid date of birth.');
        dobInput.focus();
        return false;
    }
    
    return true;
}

// Format input as user types
function formatDateInput(input) {
    let value = input.value.replace(/\D/g, ''); // Remove non-digits
    
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2);
    }
    if (value.length >= 5) {
        value = value.substring(0, 5) + '/' + value.substring(5, 9);
    }
    
    input.value = value;
}

// Initialize date picker functionality
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('dob');
    
    // Add input formatting
    dobInput.addEventListener('input', function() {
        formatDateInput(this);
    });
    
    // Add calendar picker functionality
    const calendarIcon = document.createElement('span');
    calendarIcon.innerHTML = '📅';
    calendarIcon.style.cssText = `
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        font-size: 16px;
        z-index: 10;
    `;
    
    // Create hidden date input for calendar
    const hiddenDateInput = document.createElement('input');
    hiddenDateInput.type = 'date';
    hiddenDateInput.style.cssText = `
        position: absolute;
        opacity: 0;
        pointer-events: none;
        top: 0;
        right: 0;
        width: 30px;
        height: 100%;
    `;
    hiddenDateInput.max = '<?php echo date('Y-m-d', strtotime('-18 years')); ?>';
    
    // Add calendar functionality
    calendarIcon.addEventListener('click', function() {
        hiddenDateInput.showPicker();
    });
    
    hiddenDateInput.addEventListener('change', function() {
        if (this.value) {
            const date = new Date(this.value);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            dobInput.value = `${day}/${month}/${year}`;
        }
    });
    
    // Style the container
    const formGroup = dobInput.closest('.form-group');
    const inputContainer = document.createElement('div');
    inputContainer.style.position = 'relative';
    inputContainer.style.display = 'inline-block';
    inputContainer.style.width = '100%';
    
    dobInput.parentNode.insertBefore(inputContainer, dobInput);
    inputContainer.appendChild(dobInput);
    inputContainer.appendChild(calendarIcon);
    inputContainer.appendChild(hiddenDateInput);
});

// Form validation before submission
function validateSignupForm() {
    return validateAge();
}
</script>

<div class="modal fade" id="signupform">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h3 class="modal-title">Sign Up</h3>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="signup_wrap">
            <div class="col-md-12 col-sm-6">
              <form method="post" name="signup" onsubmit="return validateSignupForm()">
                <div class="form-group">
                  <input type="text" class="form-control" name="fullname" placeholder="Full Name" required="required">
                  <small class="form-text text-muted">Enter your full name exactly as it appears on your passport</small>
                </div>
                
                <div class="form-group">
                  <input type="email" class="form-control" name="emailid" id="emailid" onBlur="checkAvailability()" placeholder="Email Address" required="required">
                  <span id="user-availability-status" style="font-size:12px;"></span> 
                </div>
                
                <div class="form-group">
                  <input type="text" class="form-control" name="mobileno" placeholder="Mobile Number" maxlength="15" required="required">
                </div>
                
                <!-- NEW: Date of Birth Field -->
                <div class="form-group">
                  <label for="dob" class="control-label">Date of Birth (DD/MM/YYYY)</label>
                  <input type="text" class="form-control" name="dob" id="dob" placeholder="DD/MM/YYYY" required="required" maxlength="10">
                  <small class="form-text text-muted">Must match your passport date of birth exactly. You must be 18+ to register.</small>
                </div>
                
                <div class="form-group">
                  <input type="password" class="form-control" id="password" name="password" placeholder="Password" required="required" minlength="6">
                  <small class="form-text text-muted">Minimum 6 characters</small>
                </div>
          
                <div class="form-group checkbox">
                  <input type="checkbox" id="terms_agree" required="required">
                  <label for="terms_agree">I Agree with <a href="#" target="_blank">Terms and Conditions</a></label>
                </div>
                
                <div class="form-group">
                  <input type="submit" value="Sign Up" name="signup" id="submit" class="btn btn-block">
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer text-center">
        <p>Already got an account? <a href="#loginform" data-toggle="modal" data-dismiss="modal">Login Here</a></p>
      </div>
    </div>
  </div>
</div>

<style>
/* Enhanced form styling */
.form-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
    display: block;
}

.form-text {
    color: #6c757d;
    margin-top: 5px;
}

.form-control[type="date"] {
    padding: 8px 12px;
}

.signup_wrap .form-group {
    margin-bottom: 20px;
}

.signup_wrap .form-group:last-child {
    margin-bottom: 0;
}

/* Date input styling */
.form-group {
    position: relative;
}

#dob {
    padding-right: 40px; /* Make room for calendar icon */
}

.date-input-container {
    position: relative;
    display: inline-block;
    width: 100%;
}

.calendar-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 16px;
    z-index: 10;
    color: #666;
    user-select: none;
}

.calendar-icon:hover {
    color: #333;
}

.hidden-date-picker {
    position: absolute;
    opacity: 0;
    pointer-events: none;
    top: 0;
    right: 0;
    width: 30px;
    height: 100%;
}

/* Enhance form field styling */
.form-control {
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
</style>