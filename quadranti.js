import * as myModule from '/CDN/scripts/module.js';
let doppie_partenze = localStorage.doppie_partenze ?? 'Doppie Partenze';
let gara_NT_36 = localStorage.gara_NT_36 ?? 'Normale';
// let maschile_femminile = localStorage.getItem('maschile_femminile') ?? 'Maschile/Femminile';
let simmetrico = localStorage.simmetrico ?? 'Asimmetrico';
// definisco window per rendere global anche su module
window.nominativo = localStorage.nominativo ?? 'Off';
window.players = localStorage.players ?? 144;
window.proette = localStorage.proette ?? 48;
window.players_x_flight = localStorage.players_x_flight ?? 3;
window.mod = players_x_flight;
window.giornata = localStorage.giornata ?? 'prima';
window.round = localStorage.round ?? '04:30';
window.start_time = localStorage.start_time ?? '08:00';
window.gap = localStorage.gap ?? '00:10';
window.compatto = localStorage.compatto ?? 'Early/Late';
window.gara_NT = localStorage.gara_NT ?? 'Gara 54 buche';
window.flight_left_women = 0;
window.strTable = '';
window.limit_1 = 0;
window.limit_2 = 0;
window.limit_3 = 0;
window.limit_1w = 0;
window.limit_2w = 0;
window.limit_3w = 0;
window.atlete = (JSON.parse(localStorage.getItem('atlete'))) ??  myModule.range(0,parseInt(proette));
window.atleti = (JSON.parse(localStorage.getItem('atleti'))) ??  myModule.range(0,parseInt(players));

$(document).ready(function() {
	// inserisco valori di default
	$('#tee_doppi').show();
	// $("#start").val($.datepicker.formatDate('dd-mm-yy', new Date()))	// valore default
	$('#gara_NT').val(gara_NT); // valore default
	$('#gara_NT_36').val(gara_NT_36); // valore default
	$('#players').val(players); // valore default
	$('#proette').val(proette); // valore default
	$('#players_x_flight').val(players_x_flight); // valore default
	$('#giornata').val(giornata); // valore default
	$('#round').val(round); // valore default
	$('#start_time').val(start_time); // valore default
	$('#gap').val(gap); // valore default
	$('.compatto').val(compatto); // valore default
	$('#doppie_partenze').val(doppie_partenze); // valore default
	// $('#maschile_femminile').val(maschile_femminile); // valore default
	$('#simmetrico').val(simmetrico); // valore default

	// leggo i valori correnti
	gap = $('#gap').val();
	start_time = $('#start_time').val();
	round = $('#round').val();
	let halfround = myModule.halftime(round);
	let cross_time = myModule.addtime(start_time, halfround);
	players = $('#players').val();
	proette = $('#proette').val();
	players_x_flight = $('#players_x_flight').val();
	giornata = $('#giornata').val();
	gara_NT = $('#gara_NT').val();
	gara_NT_36 = $('#gara_NT_36').val();
	compatto = $('.compatto').val();
	// maschile_femminile = $('#maschile_femminile').val();
	simmetrico = $('#simmetrico').val();
	doppie_partenze = $('#doppie_partenze').val();
	mod = players_x_flight;

	myModule.Datepicker();

	myModule.coordinateAjax(); // popolo con i valori di default
	$('#geo_area').change(function() {
		// secondo quanto select in geo_area
		localStorage.setItem('geo_area', $('#geo_area').val());
		localStorage.setItem('start', $('#start').val());

		myModule.coordinateAjax();
	});

	/** se la somma è inferiore a 96 (partenze a 3) o 128 (partenze a 4) ho la facoltà di fare partenze tutte la mattina e mostro il selettore **/
	if (parseInt($('#players').val()) + parseInt($('#proette').val()) <= mod * 32) $('.compatto').toggle();
	//calcolare quale sia il cross time
	$('#cross').html(cross_time);
	// if($('#gara_NT').val() == "Gara 36 buche") $('#gara_NT_36').toggle();

	// if (maschile_femminile == 'Maschile O Femminile') {
	// 	proette = 0;
	// 	$('.women').hide();
	// }
	if (doppie_partenze == 'Doppie Partenze') {
		let classifica = $('#gara_NT').val() == 'Gara 36 buche' ? ' per classifica' : '';
		// titolo la tabella in base alla giornata
		if (giornata == 'prima') $('#titolo_giornata').html('Prima Giornata');
		if (giornata == 'seconda') $('#titolo_giornata').html('Seconda Giornata' + classifica);
		// if(giornata == "final") $('#titolo_giornata').html("Finale") ;

		// definisco l'head della tabella
		strTable = '<table><tr><td>' + 'Flight' + '<td>' + 'Tee' + '<td colspan=' + mod + '>' + 'Nome' + '<td>' + 'Orario' + '<td colspan=' + mod + '>' + 'Nome' + '<td>' + 'Tee' + '<td>' + 'Flight';
		if (simmetrico == "Simmetrico") {
			strTable = myModule.tee_doppio_simmetrico(giornata);
		} else {
			strTable = myModule.tee_doppio(giornata, nominativo);
		}

		$('#first_table').html(strTable);
	} else {
		// definisco l'head della tabella
		strTable = '<table><tr><td>' + 'Flight' + '<td>' + 'Tee' + '<td colspan=' + mod + '>' + 'Nome' + '<td>' + 'Orario';
		strTable = myModule.tee_unico(simmetrico, giornata);

		$('#first_table').html(strTable);
	}
	// 	start_time = localStorage.getItem("start_time") ?? "08:00";
	// 	strTable = '<table><tr><td>' + "Flight" + '<td>' + "Tee" + '<td>' + strMod + "Orario" + '<td>' +  strMod + "Tee" + '<td>' + "Flight";
	//
	//     strTable = myModule.tee_doppio("second");
	//
	//     $('#second_table').html(strTable) ;

	$(document).on('click', '#refresh', function() {
		window.location.reload();
		localStorage.clear();
	});
	/** cattura ogni change nelle caselle input **/
	$(document).on('change', 'input[type="text"]', function() {
		// store current value
		let currId = $(this).attr('id');
		let currValue = $(this).val();
		localStorage.setItem(currId, currValue);
		// now reload and all this code runs again
		window.location.reload();
	});
	/** cattura ogni change nelle caselle select **/
	$(document).on('change', 'select', function() {
		// store current value
		let currId = $(this).attr('id');
		let currValue = $(this).val();
		// console.log(currId);
		// console.log(currValue);
		localStorage.setItem(currId, currValue);
		// now reload and all this code runs again
		window.location.reload();
	});


	/** procede al download di una vista excel **/
	$(document).on('click', '#excel', function() {
		$('#first_table').table2excel({
			exclude: '.excludeThisClass',
			name: 'Worksheet Name',
			filename: 'Simulatore Partenze.xls', // do include extension
			preserveColors: true, // set to true if you want background colors and font colors preserved
		});
	});
	/**  cambio coordinate in base al datapicker **/
	$(document).on('change', '.datepicker', function() {
		myModule.coordinateAjax();
	});
        var displayNominativo = localStorage.getItem('display1') ?? 'true';
        var displayNumerico = localStorage.getItem('display2') ?? 'false';

      if (displayNominativo == 'true') {
        $('#2').hide();
        $('#1').show();
      } else if (displayNumerico == 'true') {
        $('#1').hide();
        $('#2').show();
      }
      $(document).on('click', '#btnClick', function(e) {
        e.preventDefault();
        $('#1').hide();
        $('#2').show();
        localStorage.setItem('display2', 'true')
        localStorage.setItem('display1', 'false')
		    localStorage.setItem('nominativo', "On")
			nominativo = "On";
		    window.location.reload();
    })
      $(document).on('click', '#btnClock', function(e) {
        e.preventDefault();
        $('#2').hide();
        $('#1').show();
        localStorage.setItem('display1', 'true')
        localStorage.setItem('display2', 'false')
		    localStorage.setItem('nominativo', "Off")
			nominativo = "Off";
		    window.location.reload();
    })
	// routine per caricare i nominativi
	$("#upload").on("change", function(e) {
		e.preventDefault();
		var file = $("#file")[0].files[0];
		var formData = new FormData();
		formData.append("file", file);

		$.ajax({
			url: "/CDN/load_excel.php",
			type: "POST",
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {

				var data = JSON.parse(response);

				atlete = $.map(data[0], function(value, index) {
					return [value];
				});
				atleti = $.map(data[1], function(value, index) {
					return [value];
				});
				localStorage.setItem('atlete', JSON.stringify(atlete));
				localStorage.setItem('atleti', JSON.stringify(atleti));

				window.location.reload();
			}
		});
	});

});