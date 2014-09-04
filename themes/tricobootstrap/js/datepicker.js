$(document).ready(function(){
  var first = availability.whitelist[0];
  var last = availability.whitelist[availability.whitelist.length-1];
  var blackouts = jQuery.map(availability.blackouts, function(val, ind) {
      return val['month'] + ' ' + val['day'] + ', ' + val['year'];
    });
  var timelookup = availability.timelookup;
  $("#startdate").AnyTime_picker({ 
        format: "%b %e, %Z", 
        placement: "inline", 
        labelTitle: "Select a Start Date",
        earliest: first['date'],
        latest: last['date'],
        blackouts: blackouts
  });
  $("#starttime").AnyTime_picker({ 
        format: "%h:%i %p", 
        placement: "inline", 
        labelTitle: "Select a Start Time",
        earliest: first['firsttime'],
        latest: first['lasttime']
  });

  $("#startdate").change(function() {
      var data = $("#startdate").val();
      var time = timelookup[data];
      $("#starttime").AnyTime_setEarliest(time['firsttime']);
      $("#starttime").AnyTime_setLatest(time['lasttime']);
      $("#starttime").AnyTime_setSelected(time['firsttime']);
  });

});
