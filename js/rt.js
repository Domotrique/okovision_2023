/* global lang, Highcharts */ 
$(document).ready(function() {
    
    $.api('GET', 'graphique.getGraphe').done(function(json) {
        
        $.each(json.data, function(key, val) {
            $('#select_graphique').append('<option value="' + val.id + '">' + val.name + '</option>');
        });
        
    });
    
    $.IDify = function(text) {
        text = text.replace(/CAPPL:LOCAL\.|[\[\]]|CAPPL:/g, "");
        text = text.replace(/[\.\/]+/g, "_");
        return text;
    }
    
    
    $.connectBoiler = function() {
        
        $.api('GET', 'rt.getIndic').done(function(json) {
            
            if(json.response){
            
                $.each(json.data, function(key, val) {
                    $('#'+ $.IDify(key)).html(val);
                });
                $('#logginprogress').hide();
                $('#communication').show();
            
                
            }else{
                $('#logginprogress').hide();
                $.growlErreur(lang.error.connectBoiler);
            }
        });
    }
    
    $.hideData =function(){
        $('#logginprogress').show();
        $('#communication').hide();
    }
    
    
    
    var liveChart, refreshData;
    
    $.drawChart = function(idGraphe){
        //console.log(liveChart);
        if(typeof liveChart !== 'undefined') {
            liveChart.destroy();
            clearInterval(refreshData);
        }
        
        liveChart = new Highcharts.Chart({
            chart : {
                renderTo: 'rt',
                type: 'spline',
                zoomType: 'x',
				panning: true,
				panKey: 'shift'
            },
            plotOptions: {
				spline: {
					marker: {
						enabled: false
					}
				}
			},
			tooltip: {
				shared: true,
				crosshairs: true,
				followPointer: true
			},
			title: {
				text: ''
			},
            xAxis: {
				type: 'datetime',
				dateTimeLabelFormats: {
					minute: '%H:%M',
					hour: '%H:%M'

				},
				labels: {
					rotation: -45,
				},
				title: {
					text: lang.graphic.hour
				}
			},
			yAxis: [{
				title: {
					text: '...',
				},
				min: 0
			}],
            exporting: {
                enabled: false
            }
        
        });
        
        
        
    }
    
    $("#grapheValidate").click(function(){
        //console.log('ici');
        var idGraphe = $('#select_graphique').val();
        
        $.drawChart(idGraphe);
        liveChart.showLoading('Loading data from boiler...');
        
        $.getUpdateData(idGraphe);
        
    });
    
    
    
    
    $.getUpdateData = function(idGraphe){
        
        var firstData=0;
        refreshData = setInterval(function(){
            
             $.api('GET', 'rt.getData', {id: idGraphe}).done(function(json) {
               
               // add the point
                $.each(json , function(key, val){
                    
                    if (typeof liveChart.series[key] == 'undefined') {
                        liveChart.addSeries(val,false);
                    }else{
                        liveChart.series[key].addPoint(val.data,false);    
                    }
                    
                    
                });
                
                liveChart.redraw();
                
                if(firstData < 2){
                    
                    $.each(liveChart.series, function(key){
                        liveChart.series[key].removePoint(0);  
                    });
                    firstData = firstData + 1;
                    
                }else if(firstData == 2){
                    liveChart.hideLoading();
                }
                
             });
        },3000);
    }
    
    
    
    $("#btconfirm").click(function(e){
		var user = $('#okologin').val()
		var pass = $('#okopassword').val()
		
		if(user !== '' && pass !== ''){
		
			$.api('POST', 'rt.setOkoLogin', {user: user, pass: pass}).done(function(json) {
				$("#modal_boiler").modal('hide');
				$.hideData();
				if(!json.response){
					e.preventDefault();
					$.growlErreur(lang.error.save);
				}else{
				    $.growlValidate(lang.valid.save);
				    $.connectBoiler();
				}
				
			});
		}
		
	});
    
    $.connectBoiler();
    
});