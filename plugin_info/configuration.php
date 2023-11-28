<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}

?>
<form class="form-horizontal">
    Hi-Kumo credentials:
  <fieldset>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Email address}}
      </label>
      <div class="col-sm-4">
        <input class="configKey form-control" data-l1key="accountmail" autocomplete="off" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Password}}
      </label>
      <div class="col-sm-4">
        <input class="configKey form-control" data-l1key="accountpass" id="accountpass" type="password" autocomplete="off" /> 
        <span toggle="#accountpass" class="fa fa-fw fa-eye field-icon toggle-password"></span>
      </div>
    </div>
  </fieldset>
</form>
          
<style>
.field-icon {
  float: right;
  margin-top: -25px;
  position: relative;
  z-index: 2;
}
</style>

<script>
$(".toggle-password").click(function() {

  $(this).toggleClass("fa-eye fa-eye-slash");
  var input = $($(this).attr("toggle"));
  if (input.attr("type") == "password") {
    input.attr("type", "text");
  } else {
    input.attr("type", "password");
  }
});
</script>
