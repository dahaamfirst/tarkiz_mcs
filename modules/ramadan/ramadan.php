<?php
// تأكد من أن هذا الملف يتم تضمينه بشكل صحيح في نظام إدارة المحتوى الخاص بك
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رمضان مبارك</title>
   <style>
       .modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 20px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  position: relative;
  animation: fadeIn 0.5s ease-in;
}

.skip-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  background: none;
  border: none;
  color: #007bff;
  cursor: pointer;
  font-size: 16px;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
   </style>
   <script>
       document.addEventListener("DOMContentLoaded", function() {
  if (!localStorage.getItem("ramadanSkipped")) {
    document.getElementById("ramadan-modal").style.display = "flex";
  }
  
  document.getElementById("skip-btn").addEventListener("click", function() {
    document.getElementById("ramadan-modal").style.display = "none";
    localStorage.setItem("ramadanSkipped", "true");
  });
});
</script>
</head>
<body>

   <div id="ramadan-modal" class="modal-overlay">
  <div class="modal-content">
    <button id="skip-btn" class="skip-btn">تخطي</button>
    <h1>شهر رمضان المبارك</h1>
    <p>نرحب بكم في مدرسة أوغاريت الافتراضية، كل عام وأنتم بخير!</p>
    <img src="public_html/modules/ramadan/1.jpg" alt="فانوس رمضان">
  </div>
</div>

</body>
</html>
