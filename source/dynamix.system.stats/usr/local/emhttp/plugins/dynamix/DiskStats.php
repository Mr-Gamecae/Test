<?PHP
/* Copyright 2013, Bergware International & Andrew Hamer-Adams.
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2, or (at your option)
 * any later version.
 */
?>
<?
if ($var['fsState']=="Stopped"):
  echo "<div class='notice'>Array must be <strong><big>started</big></strong> to view disk stats.</div>";
  return;
endif;

$plugin = 'dynamix.system.stats';
$cfg = parse_ini_file("boot/config/plugins/dynamix/$plugin.cfg");

$image = "/plugins/dynamix/images";
$stats = "/plugins/dynamix/include/HardwareStats.php";
$graph = $cfg['graph'];
$frame = $cfg['frame'];
$port = $cfg['port'];
$show = $cfg['show'];
$text = $cfg['text'];
$offset = $text=='left' ? 6 : -6;
$index = $text=='left' ? 2 : 0;
$gap=0.18; $parity=0; $cache=0; $arraysize=0; $arrayfree=0;

$scale = $display['scale'];
$display['scale'] = -1; //force autoscale for chart
$series = array(); $sizes = array();
foreach ($disks as $disk) if ($disk['name']!='parity' && $disk['status']=='DISK_OK') {$series[] = "'".my_disk($disk['name'])."'"; $sizes[] = "'".my_scale($disk['size']*1024,$unit)." $unit'";}
$display['scale'] = $scale;

switch ($display['view']) {
case "small": $rowHeight = 34; $y = -2; break;
case "wide":  $rowHeight = 50; $y = 4;  break;
default:      $rowHeight = 42; $y = 0;  break;}

function bar_color($val) {
  global $cfg;
  switch (true) {
  case ($val>=$cfg['critical']):
    return "redbar";
  case ($val>=$cfg['warning']):
    return "orangebar";
  default:
    return "greenbar";}
}

foreach ($disks as $disk) {
  switch ($disk['name']) {
  case 'parity':
    $parity = $disk['sizeSb']*1024;
    break;
  case 'cache':
    $cache = $disk['sizeSb']*1024;
    break;
  case 'flash':
    $flash = $disk['size']*1024;
    break;
  default:
    $arraysize += $disk['sizeSb']*1024;
    $arrayfree += $disk['fsFree']*1024;
  }
}

$arrayused = $arraysize-$arrayfree;
$totalpercent = 100-round(100*$parity/($arraysize+$parity));
$totaldisk = 100-$totalpercent-$gap;
$freepercent = round(100*$arrayfree/$arraysize);
$arraypercent = 100-$freepercent;

if ($display['time']=="%R") {
  $hour = '%H:%M';
  $minute = '%H:%M';
  $second = '%H:%M:%S';
} else {
  $hour = '%l:%M %p';
  $minute = '%l:%M %p';
  $second = '%l:%M:%S %p';
}
?>
<link type="text/css" rel="stylesheet" href="/plugins/dynamix/styles/disk.stats.css">
<script type="text/javascript" src="/plugins/dynamix/scripts/jquery.highcharts.js"></script>
<script>
var graph = <?=$graph?>;
var frame = <?=$frame?>;
var systime,rtstime,cputime,ramtime,comtime,hddtime;
var syschart,cpuchart,ramchart,comchart,hddchart;
var interval = {1:60, 2:120, 3:300, 7:600, 14:1200, 21:1800, 31:3600, 3653:7200};

function autoscale(value,text,size) {
  var unit = ['','k','M','G','T'];
  var base = value>1?Math.floor(Math.log(value)/Math.log(1000)):0;
  var data = base<unit.length?value/Math.pow(1000, base):0;
  var scale = (data<100?100:10)/size;
  if (data==0) base=0;
  return ((Math.round(scale*data)/scale)+' '+unit[base]+text).replace('.','<?=substr($display['number'],0,1)?>');
}
<?if ($show):?>
function setChart() {
  if (graph==0) {
    setTimeout(realtime,0);
    $('#reset').show(); $('#monitor').show();
  } else {
    $('#reset').hide(); $('#monitor').hide();
  }
}
function modeller(period) {
  graph = period;
  clearTimeout(rtstime);
<?if (strpos($show,'cpu')!==false):?>
  clearTimeout(cputime); setTimeout(cpu,0);
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
  clearTimeout(ramtime); setTimeout(ram,0);
<?endif;?>
<?if (strpos($show,'com')!==false):?>
  clearTimeout(comtime); setTimeout(com,0);
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
  clearTimeout(hddtime); setTimeout(hdd,0);
<?endif;?>
  setChart();
}
function resizer(time) {
  var series, start;
  if (time<frame) {
<?if (strpos($show,'cpu')!==false):?>
    for (var i=0; i<2; i++) {
      series = cpuchart.series[i].data;
      start = series.length-time;
      while (start-- > 0) series[0].remove(false);
      cpuchart.redraw();
    }
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
    for (var i=0; i<3; i++) {
      series = ramchart.series[i].data;
      start = series.length-time;
      while (start-- > 0) series[0].remove(false);
      ramchart.redraw();
    }
<?endif;?>
<?if (strpos($show,'com')!==false):?>
    for (var i=0; i<2; i++) {
      series = comchart.series[i].data;
      start = series.length-time;
      while (start-- > 0) series[0].remove(false);
      comchart.redraw();
    }
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
    for (var i=0; i<2; i++) {
      series = hddchart.series[i].data;
      start = series.length-time;
      while (start-- > 0) series[0].remove(false);
      hddchart.redraw();
    }
<?endif;?>
  }
  frame = time;
}
function realtime() {
  var datetime = new Date();
  var timestamp = datetime.getTime();
  $.ajax({url:'<?=$stats?>', data:{type:'rts',port:'<?=$port?>'}, success:function(string) {
    var value, shift, i;
    if (graph==0) rtstime = setTimeout(realtime,1000);
    value = string.split(' ');
<?if (strpos($show,'cpu')!==false):?>
    shift = cpuchart.series[0].length==0 || cpuchart.series[0].data.length>frame;
    for (i=0; i<2; i++) cpuchart.series[i].addPoint([timestamp, (value[i+0]*1)], false, shift);
    cpuchart.redraw();
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
    shift = ramchart.series[0].length==0 || ramchart.series[0].data.length>frame;
    for (i=0; i<3; i++) ramchart.series[i].addPoint([timestamp, (value[i+4]*1)], false, shift);
    ramchart.redraw();
<?endif;?>
<?if (strpos($show,'com')!==false):?>
    shift = comchart.series[0].length==0 || comchart.series[0].data.length>frame;
    for (i=0; i<2; i++) comchart.series[i].addPoint([timestamp, (value[i+7]*1)], false, shift);
    comchart.redraw();
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
    shift = hddchart.series[0].length==0 || hddchart.series[0].data.length>frame;
    for (i=0; i<2; i++) hddchart.series[i].addPoint([timestamp, (value[i+2]*1)], false, shift);
    hddchart.redraw();
<?endif;?>
  }});
}
<?endif;?>
function systemStats() {
  clearTimeout(systime);
  if (graph>0) {
<?if (strpos($show,'cpu')!==false):?>
    setTimeout(cpu,0);
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
    setTimeout(ram,0);
<?endif;?>
<?if (strpos($show,'com')!==false):?>
    setTimeout(com,0);
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
    setTimeout(hdd,0);
<?endif;?>
  }
}
function diskStats() {
  setTimeout('sys(0)',0);
  if (graph>0) {
<?if (strpos($show,'cpu')!==false):?>
    clearTimeout(cputime);
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
    clearTimeout(ramtime);
<?endif;?>
<?if (strpos($show,'com')!==false):?>
    clearTimeout(comtime);
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
    clearTimeout(hddtime);
<?endif;?>
  }
}
function sys(delay) {
  var series = [], i = 0;
  if (delay!=0) delay = 1200;
  $.ajax({url:'<?=$stats?>', data:{type:'sys',warning:'<?=$cfg['warning']?>',critical:'<?=$cfg['critical']?>'}, success:function(string) {
<?if ($display['refresh']>0 || ($display['refresh']<0 && $var['mdResync']==0)):?>
    if ($('#tab1').is(':checked')) systime = setTimeout('sys(0)',30000);
<?endif;?>
    if (!syschart.series.length) {
      $.each($.parseJSON(string), function(k,v) {series.name = k; series.data = v; syschart.addSeries(series, false);});
    } else {
      $.each($.parseJSON(string), function(k,v) {series.data = v; syschart.series[i++].setData(series.data, false);});
    }
    syschart.redraw();
<?if ($text=='left'):?>
    $.each(syschart.series[2].data, function(k,v) {setTimeout(function() {v.dataLabel.css({opacity:1});}, delay);});
<?else:?>
    $.each(syschart.series[0].data, function(k,v) {setTimeout(function() {v.dataLabel.css({opacity:1});}, delay); if (v.total<4) v.dataLabel.attr({x:6});});
<?endif;?>
  }});
  $.ajax({url:'<?=$stats?>', data:{type:'sum',plugin:'<?=$plugin?>'}, success:function(string) {
    var data = string.split(';');
    $('#totalarray').attr('class',data[0]).css('width',data[1]);
    $('#stats1').attr('class',data[2]);
    $('#stats2').html(data[3]);
    $('#stats3').html(data[4]);
  }});
}
<?if (strpos($show,'cpu')!==false):?>
function cpu() {
  var series = [], i = 0;
  $.ajax({url:'<?=$stats?>', data:{type:'cpu',graph:graph}, success:function(string) {
<?if ($display['refresh']>0 || ($display['refresh']<0 && $var['mdResync']==0)):?>
    if (graph>0 && $('#tab2').is(':checked')) cputime = setTimeout(cpu,interval[graph]*1000);
<?endif;?>
    if (!cpuchart.series.length) {
      $.each($.parseJSON(string), function(k,v) {series.name = k; series.data = v; cpuchart.addSeries(series, false);});
    } else {
      $.each($.parseJSON(string), function(k,v) {series.data = v; cpuchart.series[i++].setData(series.data, false);});
    }
    cpuchart.redraw();
  }});
}
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
function ram() {
  var series = [], i = 0;
  $.ajax({url:'<?=$stats?>', data:{type:'ram',graph:graph}, success:function(string) {
<?if ($display['refresh']>0 || ($display['refresh']<0 && $var['mdResync']==0)):?>
    if (graph>0 && $('#tab2').is(':checked')) ramtime = setTimeout(ram,interval[graph]*1000);
<?endif;?>
    if (!ramchart.series.length) {
      $.each($.parseJSON(string), function(k,v) {series.name = k; series.data = v; ramchart.addSeries(series, false);});
    } else {
      $.each($.parseJSON(string), function(k,v) {series.data = v; ramchart.series[i++].setData(series.data, false);});
    }
    ramchart.redraw();
  }});
}
<?endif;?>
<?if (strpos($show,'com')!==false):?>
function com() {
  var series = [], i = 0;
  $.ajax({url:'<?=$stats?>', data:{type:'com',port:'<?=$port?>',graph:graph}, success:function(string) {
<?if ($display['refresh']>0 || ($display['refresh']<0 && $var['mdResync']==0)):?>
    if (graph>0 && $('#tab2').is(':checked')) comtime = setTimeout(com,interval[graph]*1000);
<?endif;?>
    if (!comchart.series.length) {
      $.each($.parseJSON(string), function(k,v) {series.name = k; series.data = v; comchart.addSeries(series, false);});
    } else {
      $.each($.parseJSON(string), function(k,v) {series.data = v; comchart.series[i++].setData(series.data, false);});
    }
    comchart.redraw();
  }});
}
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
function hdd() {
  var series = [], i = 0;
  $.ajax({url:'<?=$stats?>', data:{type:'hdd',graph:graph}, success:function(string) {
<?if ($display['refresh']>0 || ($display['refresh']<0 && $var['mdResync']==0)):?>
    if (graph>0 && $('#tab2').is(':checked')) hddtime = setTimeout(hdd,interval[graph]*1000);
<?endif;?>
    if (!hddchart.series.length) {
      $.each($.parseJSON(string), function(k,v) {series.name = k; series.data = v; hddchart.addSeries(series, false);});
    } else {
      $.each($.parseJSON(string), function(k,v) {series.data = v; hddchart.series[i++].setData(series.data, false);});
    }
    hddchart.redraw();
  }});
}
<?endif;?>
function scrollBarWidth() {
  $('body').append('<div id="fakescrollbar" style="width:50px;height:50px;overflow:hidden;position:absolute;top:-200px;left:-200px;"></div>');
  var fakeScrollBar = $('#fakescrollbar');
  fakeScrollBar.append('<div style="height:100px;">&nbsp;</div>');
  var w1 = fakeScrollBar.find('div').innerWidth();
  fakeScrollBar.css('overflow-y','scroll');
  var w2 = $('#fakescrollbar').find('div').html('required to init new width.').innerWidth();
  fakeScrollBar.remove();
  return (w1-w2);
}
function getWidth(full) {
  var width = $(window).width();
  if (width>1240) width = 1240; else if (width<984) width = 984; else width -= scrollBarWidth();
  return full ? width : (width-<?=10*$cfg['cols']+($cfg['cols']?10:0)?>)/<?=($cfg['cols']+1)?>;
}
$(function() {
  $('#tab1').bind({click:function(){$('#selector').hide();diskStats();}});
<?if ($show):?>
  $('#tab2').bind({click:function(){$('#selector').show();systemStats();}});
  setChart();
<?else:?>
  $('label[for=tab2]').hide();
<?endif;?>
  $('#totalarray').animate({width:'<?=$arraypercent?>%'}, 1500);
  Highcharts.setOptions({
    global:{useUTC:false},
    chart:{
      style:{fontFamily:'arimo,arial,sans-serif',fontSize:'10px'},
      backgroundColor:{linearGradient:{x1:0,y1:0,x2:0,y2:1},stops:[[0,'rgb(96,96,96)'],[1,'rgb(16,16,16)']]},
      borderWidth:0,
      borderRadius:8,
      plotBackgroundColor:null,
      plotBorderWidth:0,
      plotShadow:false,
      type:'area',
      margin:[55,<?=$cfg['size']?'70':'20'?>,35,70],
      spacingTop:15,
      spacingRight:0,
      spacingBottom:0,
      spacingLeft:0,
      height:260,
      width:getWidth(false),
      animation:false,
      zoomType:'x'
    },
    title:{style:{color:'#fff',fontSize:'18px'},y:10},
    subtitle:{style:{color:'#aaa',fontSize:'13px'},y:28},
    plotOptions:{
      area:{marker:{enabled:false},lineWidth:1,states:{hover:{lineWidth:1}},shadow:false,turboThreshold:1},
      bar:{borderWidth:0,borderRadius:2,states:{hover:{enabled:false}},shadow:false,turboThreshold:1},
      series:{animation:false}
    },
    xAxis:{
      type:'datetime',
      dateTimeLabelFormats:{second:'<?=$second?>',minute:'<?=$minute?>',hour:'<?=$hour?>'},
      gridLineWidth:0,
      lineColor:'#999',
      tickColor:'#999',
      labels:{style:{color:'#999',fontSize:'10px'},y:20}
    },
    yAxis:{
      title:{text:null},
      gridLineColor:'rgba(255,255,255,.1)',
      lineWidth:0,
      tickWidth:0,
      labels:{style:{color:'#999',fontSize:'11px'},y:2},
      min:0
    },
    tooltip:{
      backgroundColor:{linearGradient:{x1:0,y1:0,x2:0,y2:1},stops:[[0,'rgba(96,96,96,.8)'],[1,'rgba(16,16,16,.8)']]},
      borderWidth:0,
      shared:true,
      style:{color:'#fff',fontSize:'10px'},
      positioner:function() {return {x:70,y:5};}
    },
    legend:{
      borderWidth:0,
      align:'right',
      verticalAlign:'top',
      layout:'vertical',
      x:-22,
      y:-4,
      floating:true,
      itemStyle:{color:'#ccc',fontSize:'9px'},
      itemMarginBottom:2,
      itemHoverStyle:{color:'yellow'},
      itemHiddenStyle:{color:'#999'}
    },
    exporting:{enabled:false},
    credits:{enabled:false}
  });
  syschart = new Highcharts.Chart({
    chart:{renderTo:'sys',events:{load:sys},type:'bar',height:<?=count($disks)*$rowHeight?>,width:getWidth(true),zoomType:null},
    colors:[{linearGradient:{x1:0,y1:0,x2:0,y2:1},stops:[[0,'#941C00'],[1,'#DE1100']]},{linearGradient:{x1:0,y1:0,x2:0,y2:1},stops:[[0,'#CE7C10'],[1,'#F0B400']]},{linearGradient:{x1:0,y1:0,x2:0,y2:1},stops:[[0,'#17BF0B'],[1,'#127A05']]}],
    plotOptions:{series:{stacking:'normal',animation:{duration:1000},pointPadding:0.2,groupPadding:0,
    dataLabels:{enabled:true,color:'#fff',align:'<?=$text?>',verticalAlign:'top',x:<?=$offset?>,y:<?=$y?>,style:{opacity:0},formatter:function(){if (this.series.index==<?=$index?>) return this.total+' %';}}}},
    title:{text:'Disk Usage'},
    subtitle:{text:'utilization in percentage'},
<?if ($cfg['size']):?>
    xAxis:[{alternateGridColor:'rgba(255,255,255,.018)',type:'linear',labels:{style:{fontSize:'12px'},y:5},lineWidth:0,tickWidth:0,categories:[<?=implode(',',$series)?>]},
    {opposite:true,linkedTo:0,type:'linear',labels:{style:{fontSize:'12px'},y:5},lineWidth:0,tickWidth:0,categories:[<?=implode(',',$sizes)?>]}],
<?else:?>
    xAxis:{alternateGridColor:'rgba(255,255,255,.018)',type:'linear',labels:{style:{fontSize:'12px'},y:5},lineWidth:0,tickWidth:0,categories:[<?=implode(',',$series)?>]},
<?endif;?>
    yAxis:{gridLineDashStyle:'dash',labels:{y:15},max:100,plotLines:[
      {label:{text:'<?=$cfg['warning']?>',style:{color:'#ff6600',fontSize:'9px'},verticalAlign:'top',textAlign:'right',y:-4,x:-4},dashStyle:'dash',color:'#ff6600',width:0.8,value:<?=$cfg['warning']?>,zIndex:5},
      {label:{text:'<?=$cfg['critical']?>',style:{color:'#ff6600',fontSize:'9px'},verticalAlign:'top',textAlign:'right',y:-4,x:-4},dashStyle:'dash',color:'#ff6600',width:0.8,value:<?=$cfg['critical']?>,zIndex:5}
    ]},
    legend:{enabled:false},
    tooltip:{enabled:false}
  },function(chart){
    chart.renderer.image('<?=$image?>/sys.png',18,8,32,32).add();
  });
<?if (strpos($show,'cpu')!==false):?>
  cpuchart = new Highcharts.Chart({
    chart:{renderTo:'cpu',events:{load:cpu}},
    colors:['#AFD8F8','#EDC240'],
    plotOptions:{area:{stacking:'normal'}},
    title:{text:'Processor'},
    subtitle:{text:'CPU Load'},
    yAxis:{labels:{formatter:function(){return this.value+' %';}}},
    tooltip:{formatter:function(){
     var s='<span style="color:#ccc;font-size:9px;">'+Highcharts.dateFormat('%A, %b %e, %H:%M',this.x)+'</span>';
     $.each(this.points,function(i,point){s+='<br><span style="color:'+point.series.color+'">'+point.series.name+':</span>'+autoscale(point.y,'%',10)});
     return s;
    }}
  },function(chart){
    chart.renderer.image('<?=$image?>/cpu.png',18,8,32,32).add();
  });
<?endif;?>
<?if (strpos($show,'ram')!==false):?>
  ramchart = new Highcharts.Chart({
    chart:{renderTo:'ram',events:{load:ram}},
    colors:['#4DA74D','#CC6600','#EDC240'],
    plotOptions:{area:{stacking:'normal'}},
    title:{text:'Memory'},
    subtitle:{text:'RAM'},
    yAxis:{labels:{formatter:function() {return autoscale(this.value*1000,'B',10);}}},
    legend:{y:-11},
    tooltip:{formatter:function(){
     var s='<span style="color:#ccc;font-size:9px;">'+Highcharts.dateFormat('%A, %b %e, %H:%M',this.x)+'</span>';
     $.each(this.points,function(i,point){s+='<br><span style="color:'+point.series.color+'">'+point.series.name+':</span>'+autoscale(point.y*1000,'B',1)});
     return s;
    }}
  },function(chart){
    chart.renderer.image('<?=$image?>/ram.png',18,8,32,32).add();
  });
<?endif;?>
<?if (strpos($show,'com')!==false):?>
  comchart = new Highcharts.Chart({
    chart:{renderTo:'com',events:{load:com}},
    colors:['#CC6600','#EDC240'],
    title:{text:'Network'},
    subtitle:{text:'<?=$cfg['port']?>'},
    yAxis:{labels:{formatter:function() {return autoscale(this.value*<?=$cfg["unit"]=='b'?8000:1000?>,'<?=$cfg["unit"]?>/s',10);}}},
    tooltip:{formatter:function(){
     var s='<span style="color:#ccc;font-size:9px;">'+Highcharts.dateFormat('%A, %b %e, %H:%M',this.x)+'</span>';
     $.each(this.points,function(i,point){s+='<br><span style="color:'+point.series.color+'">'+point.series.name+':</span>'+autoscale(point.y*<?=$cfg["unit"]=='b'?8000:1000?>,'<?=$cfg["unit"]?>/s',1)});
     return s;
    }}
  },function(chart){
    chart.renderer.image('<?=$image?>/com.png',18,8,32,32).add();
  });
<?endif;?>
<?if (strpos($show,'hdd')!==false):?>
  hddchart = new Highcharts.Chart({
    chart:{renderTo:'hdd',events:{load:hdd}},
    colors:['#CC6600','#EDC240'],
    title:{text:'Storage'},
    subtitle:{text:'<?=exec("ls /dev/[hs]d[a-z] | wc -l")?> disks'},
    yAxis:{labels:{formatter:function() {return autoscale(this.value*512,'B/s',10);}}},
    tooltip:{formatter:function(){
     var s='<span style="color:#ccc;font-size:9px;">'+Highcharts.dateFormat('%A, %b %e, %H:%M',this.x)+'</span>';
     $.each(this.points,function(i,point){s+='<br><span style="color:'+point.series.color+'">'+point.series.name+':</span>'+autoscale(point.y*512,'B/s',1)});
     return s;
    }}
  },function(chart){
    chart.renderer.image('<?=$image?>/hdd.png',18,8,32,32).add();
  });
<?endif;?>
});
</script>
<div class="mybar whitebar" style="float:left; width:<?=$totalpercent?>%;"><span id="totalarray" class="mybar <?=bar_color($arraypercent)?> align-left" style="width:0"></span></div>
<div class="mybar graybar align-right" style="width:<?=$totaldisk?>%"></div>
<div class="align-left"><img src="<?=$image?>/array.png" class="image-left"><strong><?=my_scale($arraysize, $unit)." $unit"?></strong><br><small>Total Array Size</small></div>
<div class="align-left"><span id="stats1" class="mybar <?=bar_color($arraypercent)?> inside"></span><span id="stats2"><strong> <?=my_scale($arrayused, $unit)." $unit"?> <img src="<?=$image?>/arrow.png" style="margin-top:-3px;"> <?=$arraypercent?>%</strong><br/><small>Total Space Used</small></span></div>
<div class="align-left"><span class="mybar whitebar inside"></span><span id="stats3"><strong><?=my_scale($arrayfree, $unit)." $unit"?> <img src="<?=$image?>/arrow.png" style="margin-top:-3px;"> <?=$freepercent?>%</strong><br/><small>Available for Data</small></span></div>
<div class="align-right"><span class="mybar graybar inside"></span><strong> <?=my_scale($parity, $unit)." $unit"?></strong><br><small>Used for Parity</small></div>
<div class="align-right"><span class="mybar redbar inside"></span><strong>Above <?=$cfg['critical']?>%</strong><br><small>Low on Space</small></div>
<?if ($cfg['warning']<$cfg['critical']):?>
<div class="align-right"><span class="mybar orangebar inside"></span><strong>Above <?=$cfg['warning']?>%</strong><br><small>High on Usage</small></div>
<?endif;?>
<div class="align-right"><span class="mybar greenbar inside"></span><strong>Below <?=min($cfg['warning'],$cfg['critical'])?>%</strong><br><small>Normal Usage</small></div>
<span id="sys" class="graph1"></span>