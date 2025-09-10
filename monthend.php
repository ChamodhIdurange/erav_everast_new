<?php
include "include/header.php";
include "include/topnavbar.php";
?>
<div id="layoutSidenav">
  <div id="layoutSidenav_nav">
    <?php include "include/menubar.php"; ?>
  </div>
  <div id="layoutSidenav_content">
    <main>
      <div class="page-header page-header-light bg-white shadow">
        <div class="container-fluid">
          <div class="page-header-content py-3">
            <h1 class="page-header-title">
              <div class="page-header-icon"><i class="far fa-calendar-alt"></i></div>
              <span>Month End</span>
            </h1>
          </div>
        </div>
      </div>

      <div class="container-fluid mt-2 p-0 p-2">
        <div class="card">
          <div class="card-body p-0 p-2">
            <div class="row">
              <div class="col-3">
                <form id="formmonthend" action="process/monthendprocess.php" method="post" autocomplete="off">
                  <div class="form-group mb-1">
                    <label class="small font-weight-bold text-dark">Select Date*</label>
                    <div class="input-group input-group-sm">
                      <input type="date" class="form-control" name="monthend_date" id="monthend_date" required>
                      <div class="input-group-append">
                        <span class="btn btn-light border-gray-500"><i class="far fa-calendar"></i></span>
                      </div>
                    </div>
                    <small id="monthLabel" class="text-muted d-block mt-1"></small>
                  </div>

                  <div class="form-group mt-3">
                    <button type="button" id="submitBtn" class="btn btn-outline-primary btn-sm px-3 fa-pull-right">
                      <i class="far fa-save"></i>&nbsp;Month End
                    </button>
                    <input type="submit" class="d-none" id="hidebtnsubmit">
                  </div>
                </form>

                <div class="mt-3">
                  <?php if (!empty($_SESSION['msg'])): ?>
                    <div class="alert alert-info py-2 px-3"><?php echo $_SESSION['msg'];
                                                            unset($_SESSION['msg']); ?></div>
                  <?php endif; ?>
                  <div id="inlineAlert" class="alert d-none py-2 px-3"></div>
                </div>
              </div>

              <div class="col-9">
                <table class="table table-bordered table-striped table-sm nowrap small w-100" id="dataTable">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Month</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    <?php include "include/footerbar.php"; ?>
  </div>
</div>
<?php include "include/footerscripts.php"; ?>

<script>
  $(document).ready(function() {
    const $date = $('#monthend_date');
    const $submitBtn = $('#submitBtn');
    const $inlineAlert = $('#inlineAlert');
    const $monthLabel = $('#monthLabel');

    const today = new Date();
    $date.val(today.toISOString().split('T')[0]); 
    updateMonthLabel();

    const dt = $('#dataTable').DataTable({
      destroy: true,
      processing: true,
      serverSide: true,
      ajax: {
        url: "scripts/monthendlist.php",
        type: "POST"
      },
      order: [
        [1, "desc"]
      ],
      columns: [{
          data: "rownum",
          className: "text-right",
          width: "50px"
        },
        {
          data: "month_name"
        },
        {
          data: "status_badge",
          className: "text-center"
        },
      ]
    });

    $date.on('change', function() {
      updateMonthLabel();
      checkMonthStatus();
    });

    checkMonthStatus();

    $submitBtn.on('click', function() {
      if (!$date.val()) {
        showInline('Please select a date.', 'warning');
        return;
      }
      const pretty = monthPretty($date.val());
      if (confirm("Submit Month End for " + pretty + " ?")) {
        $("#hidebtnsubmit").click();
      }
    });

    function monthPretty(dateStr) {
      const d = new Date(dateStr);
      return d.toLocaleString('default', {
        month: 'long'
      }) + ' ' + d.getFullYear();
    }

    function updateMonthLabel() {
      const v = $date.val();
      $monthLabel.text(v ? 'Selected: ' + v : '');
    }

    function showInline(msg, type) {
      $inlineAlert.removeClass('d-none alert-info alert-success alert-warning alert-danger')
        .addClass('alert-' + type).text(msg);
    }

    function hideInline() {
      $inlineAlert.addClass('d-none').text('');
    }

    function checkMonthStatus() {
      hideInline();
      $submitBtn.prop('disabled', true);
      const v = $date.val();
      if (!v) return;
      const ym = v.substr(0, 7);
      $.ajax({
        url: "scripts/check_month_status.php",
        method: "POST",
        dataType: "json",
        data: {
          ym: ym
        },
        success: function(resp) {
          if (resp.submitted) {
            $submitBtn.prop('disabled', true);
            showInline('Month End already submitted for ' + monthPretty(v), 'info');
          } else {
            $submitBtn.prop('disabled', false);
          }
        },
        error: function() {
          $submitBtn.prop('disabled', false);
        }
      });
    }
  });
</script>
<?php include "include/footer.php"; ?>