<?php

require('includes/application_top.php');

require_once (DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
bx_dependency_resolver::require('modified_github');

require_once(DIR_WS_INCLUDES . 'head.php');
?>
</head>
<body>
	<?php require(DIR_WS_INCLUDES . 'header.php'); ?>

  <table class="tableBody">
    <tr>
      <?php
      if (USE_ADMIN_TOP_MENU == 'false') {
        echo '<td class="columnLeft2">'.PHP_EOL;
        require_once(DIR_WS_INCLUDES.'column_left.php');
        echo '</td> <!-- .columnLeft2 -->'.PHP_EOL;
      }
      ?>
      <td class="boxCenter">
        <div class="pageHeadingImage" style="min-width: 45px;">
          <?php echo xtc_image(DIR_WS_ICONS . 'heading/bx_github_repositories.png', BX_GITHUB_REPOSITORIES_HEADING_TITLE, '', '', 'style="max-height: 32px;"'); ?>
        </div>
        <div class="pageHeading flt-l">
          <div class="pageHeading"><?php echo BX_GITHUB_REPOSITORIES_HEADING_TITLE; ?></div>
          <div class="main pdg2 flt-l"><?php echo BX_GITHUB_REPOSITORIES_HEADING_SUB_TITLE; ?></div>
        </div>
        <div class="clear"></div>

        <table class="tableCenter">
          <tr>
            <td class="boxCenterLeft">
              <div id="headboard">
                <div class="main">
                  <strong><?php echo BX_GITHUB_REPOSITORIES_HEADING_TITLE; ?></strong>
                </div>
              </div>
              <p><?php echo BX_GITHUB_REPOSITORIES_TEXT_INTRO; ?></p>

            </td>

            <td class="boxRight">
  <?php

    $heading  = array();
    $contents = array();

    $heading[]  = array('text' => '<strong>HEADING</strong>');
    $contents[] = array('text' => 'Text');

    if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
      $box = new box;
      echo $box->infoBox($heading, $contents);
    }
  ?>
            </td> <!-- .boxRight -->
          </tr>
        </table> <!-- .tableCenter -->
      </td> <!-- .boxCenter -->
    </tr>
  </table>

<?php require(DIR_WS_INCLUDES.'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES.'application_bottom.php'); ?>

