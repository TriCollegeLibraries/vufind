/*global path*/

function checkItemStatuses() {
  var id = $.map($('.ajaxItem'), function(i) {
    return $(i).find('.hiddenId')[0].value;
  });
  if (!id.length) {
    return;
  }
  
  $(".ajax-availability").show();
  $.ajax({
    dataType: 'json',
    url: path + '/AJAX/JSON?method=getItemStatuses',
    data: {id:id},
    success: function(response) {
      if(response.status == 'OK') {
        $.each(response.data, function(i, result) {
          var item = $($('.ajaxItem')[result.record_number]);

          item.find('.status').empty().append(result.availability_message);
          if (typeof(result.full_status) != 'undefined'
            && result.full_status.length > 0
            && item.find('.callnumAndLocation').length > 0
          ) {
            // Full status mode is on -- display the HTML and hide extraneous junk:
            item.find('.callnumAndLocation').empty().append(result.full_status);
            item.find('.callnumber').hide();
            item.find('.location').hide();
            item.find('.hideIfDetailed').hide();
            item.find('.status').hide();
          } else if (typeof(result.missing_data) != 'undefined'
            && result.missing_data
          ) {
            // No data is available -- hide the entire status area:
            item.find('.callnumAndLocation').hide();
            item.find('.status').hide();
          } else if (result.locationList) {
            // We have multiple locations -- build appropriate HTML and hide unwanted labels:
            item.find('.callnumber').hide();
            item.find('.hideIfDetailed').hide();
            item.find('.location').hide();
            var locationListHTML = "";
            for (var x=0; x<result.locationList.length; x++) {
              locationListHTML += '<div class="groupLocation">';
              if (result.locationList[x].availability) {
                locationListHTML += '<i class="icon-ok text-success"></i> <span class="text-success">'
                  + result.locationList[x].location + '</span> ';
              } else {
                locationListHTML += '<i class="icon-remove text-error"></i> <span class="text-error"">'
                  + result.locationList[x].location + '</span> ';
              }
              locationListHTML += '</div>';
              locationListHTML += '<div class="groupCallnumber">';
              locationListHTML += (result.locationList[x].callnumbers)
                   ?  result.locationList[x].callnumbers : '';
              locationListHTML += '</div>';
            }
            item.find('.locationDetails').show();
            item.find('.locationDetails').empty().append(locationListHTML);
          } else {
            // Default case -- load call number and location into appropriate containers:
            item.find('.callnumber').empty().append(result.callnumber+'<br/>');
            item.find('.location').empty().append(
              result.reserve == 'true'
              ? result.reserve_message
              : result.location
            );
            // trico edit 2014.04 ah - removed the colored boxes
            item.find('.status').empty().append(result.availability_container);
            item.find('.status').children('span').empty().append(result.availability_message);
          }
        });
      } else {
        // display the error message on each of the ajax status place holder
        $(".ajax-availability").empty().append(response.data);
      }
      $(".ajax-availability").removeClass('ajax-availability');
    }
  });
}

$(document).ready(function() {
  checkItemStatuses();
});
