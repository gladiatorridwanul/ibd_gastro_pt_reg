<?php
http_response_code(403);
include __DIR__.'/header.php';
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card border-danger">
        <div class="card-header bg-danger text-white">
          <h5 class="mb-0"><i class="fa-solid fa-ban"></i> Access Denied</h5>
        </div>
        <div class="card-body text-center">
          <i class="fa-solid fa-lock fa-4x text-danger mb-3"></i>
          <h4 class="text-danger">403 - Forbidden</h4>
          <p class="text-muted">You don't have permission to access this page.</p>
          <hr>
          <p class="small">If you believe this is an error, please contact your administrator.</p>
          <a href="?r=dashboard/index" class="btn btn-primary">
            <i class="fa-solid fa-home"></i> Go to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__.'/footer.php'; ?>