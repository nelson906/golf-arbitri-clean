export function coordinateAjax() {
  let geo_area = localStorage.hasOwnProperty("geo_area") ?
    localStorage.geo_area :
    (localStorage.geo_area = "NORD OVEST");
  let start = localStorage.hasOwnProperty("start") ?
    localStorage.start :
    localStorage.setItem(
      "start",
      $.datepicker.formatDate("dd-mm-yy", new Date())
    );

  $("#geo_area").val(geo_area);
  $("#start").val(start);

  $.ajax({
    url: "/CDN/coordinate-ajax.php",
    type: "POST",
    dataType: "json",
    data: { geo_area: geo_area, start: start }
  }).done(function(data) {
    $("#sunrise").html(data["sunrise"]);
    $("#sunset").html(data["sunset"]);
  });
}


export function Datepicker() {
  $.datepicker.regional["it"] = {
    closeText: "Chiudi",
    prevText: "&#x3c;Prec",
    nextText: "Succ&#x3e;",
    currentText: "Oggi",
    monthNames: [
      "Gennaio",
      "Febbraio",
      "Marzo",
      "Aprile",
      "Maggio",
      "Giugno",
      "Luglio",
      "Agosto",
      "Settembre",
      "Ottobre",
      "Novembre",
      "Dicembre"
    ],
    monthNamesShort: [
      "Gen",
      "Feb",
      "Mar",
      "Apr",
      "Mag",
      "Giu",
      "Lug",
      "Ago",
      "Set",
      "Ott",
      "Nov",
      "Dic"
    ],
    dayNames: [
      "Domenica",
      "Luned&#236",
      "Marted&#236",
      "Mercoled&#236",
      "Gioved&#236",
      "Venerd&#236",
      "Sabato"
    ],
    dayNamesShort: ["Dom", "Lun", "Mar", "Mer", "Gio", "Ven", "Sab"],
    dayNamesMin: ["Do", "Lu", "Ma", "Me", "Gi", "Ve", "Sa"],
    weekHeader: "Sm",
    dateFormat: "dd/mm/yy",
    firstDay: 1,
    isRTL: false,
    showMonthAfterYear: false,
    yearSuffix: ""
  };

  $.datepicker.setDefaults($.datepicker.regional["it"]);

  $(".datepicker").datepicker({
    dateFormat: "dd-mm-yy"
  });
  // .on('change', function() { coordinateAjax() });		// cambio coordinate in base al datapicker
}
// !!! funzione range per avere un array da un numero ad un altro
export const range = (start, end) => {
  let output = [];
  if (typeof end === "undefined") {
    end = start;
    start = 0;
  }
  for (let i = start; i <= end; i++) {
    output.push(i);
  }
  return output;
};
// trasforma minuti totali in formato hh:mm
export const formatMinutes = (totalMinutes) => {
  var hours = Math.floor(totalMinutes / 60);
  var minutes = totalMinutes % 60;

  return hours + ":" + minutes;
};
// somma due orari
export const addtime = (a, b) => {
  let arr = [a, b];
  let h = 0,
    m = 0;

  arr.map((value, i) => {
    h = h + +value.toString().split(":")[0];
    m = m + +value.toString().split(":")[1];
  });
  h = parseInt(h);
  m = parseInt(m);
  if (m >= 60) {
    m = m - 60;
    h = h + 1;
  }
  if (h < 10) h = "0" + h;
  if (m < 10) m = "0" + m;
  return h + ":" + m;
};
// divide a metà un orario tipo durata gara
export const halftime = (a) => {
  let arr = [a];
  let h = 0,
    m = 0;

  arr.map((value, i) => {
    h = h + +value.toString().split(":")[0];
    m = m + +value.toString().split(":")[1];
  });
  h = parseInt(h);
  m = parseInt(m);
  h = h / 2;
  m = m / 2;
  return h + ":" + m;
};
// verifica se il valore n è tra a e b
const isBetween = (n, a, b) => {
  return (n - a) * (n - b) <= 0;
};
// definisco l'array numerico o nominativo a seconda se
// su quadranti.js sia stato selezionato usando "atlete" e non localstore
const set_nominativo = () => {
  atlete = nominativo === "On" ? atlete : range(0, parseInt(proette));
  atleti = nominativo === "On" ? atleti : range(0, parseInt(players));
};
// se l'array non è "completo" inserisco i "vuoti" in modo da averli in "testa"
// se l'array è in discesa i vuoti vanno inseriti in coda, altrimenti in testa
const mod_correction = (array, up_down) => {
  let value = array.length % mod;
  // array in salita inserisco i "vuoti" in testa all'array
  if (up_down === "arrow_up") {
    if (value === mod - 3 && value !== 0) {
      array.splice(mod - 1, 0, "");
      array.splice(2 * mod - 1, 0, "");
      array.splice(3 * mod - 1, 0, "");
    }
    if (value === mod - 2 && value !== 0) {
      array.splice(mod - 1, 0, "");
      array.splice(2 * mod - 1, 0, "");
    }
    if (value === mod - 1 && value !== 0) {
      array.splice(mod - 1, 0, "");
    }
  }
  // array in discesa inserisco i "vuoti" in fondo all'array
  if (up_down === "arrow_down") {
    if (value === mod - 3 && value !== 0) {
      array.splice(array.length, 0, "");
      array.splice(array.length - mod, 0, "");
      array.splice(array.length - 2 * mod, 0, "");
    }
    if (value === mod - 2 && value !== 0) {
      array.splice(array.length, 0, "");
      array.splice(array.length - mod, 0, "");
    }
    if (value === mod - 1 && value !== 0) {
      array.splice(array.length, 0, "");
    }
  }
};
// !!! funzione range per avere un array da un numero ad un altro anche nominativo
const range_nominativo = (start, end, array, inverso) => {
  let output = [];
  let up_down = "arrow_down";

  if (typeof end === "undefined") {
    end = start;
    start = 0;
  }
  if (inverso !== "inverse") {
    for (let i = start; i <= end; i++) {
      output.push(array[i]);
    }
  } else {
    for (let i = end; i >= start; i--) {
      output.push(array[i]);
      up_down = "arrow_up";
    }
  }

  // se l'array non è "completo" inserisco i "vuoti" in modo da averli in "testa"
  // modificare mod_correction se l'array è a scendere o salire. Default up_down=>arrow_down
  mod_correction(output, up_down);
  return output;
};
// costruisce un array con chunk definiti da "mod"
const build_array = (start, end, array, inverso) => {
  // !!! da un array estraggo chunk di 3 e creo un nuovo array
  const items = range_nominativo(start, end, array, inverso); //… your array, filled with values

  const result = new Array(Math.ceil(items.length / mod))
    .fill()
    .map((_) => items.splice(0, mod));
  //
  return result;
};
// costruisce la tabella inserendo i colori desiderati
const build_rows = (array1, array2, colorA, colorB, colorC) => {
  let longest = Math.max(array1.length, array2.length);
  for (var i = 0; i < longest; i++) {
    $.each(array1[i], function(index, value) {
      // inserisco cambio riga e casella tee e conteggio flight
      if (index == 0) {
        strTable += "<tr>";
        strTable +=
          '<td style="background-color:' +
          colorB +
          '; color: black">' +
          (parseInt(i) + 1);
        strTable += '<td style="color: black">' + 1;
      }
      strTable += '<td style="color: ' + colorA + '">' + value;
      // console.log("longest : " + longest + "  i :" + (i+2))
    });
    // nel caso di differenza di array aggiungo una riga vuota. Necessario solo su gare 36 buche
    if (array1.length < array2.length && longest == i + 1) {
      strTable += "<tr>";
      strTable += "<td>";
      strTable += "<td>";
      strTable += "<td colspan=" + mod + ">";
    }

    strTable += "<td>" + start_time;
    $.each(array2[i], function(index, value) {
      strTable += '<td style="color: ' + colorA + '">' + value;
    });
    // aggiungo riga vuota quando i due array sono differenti
    if (array1.length > array2.length && longest === i + 1) {
      strTable += "<td colspan=" + mod + ">";
    }
    // inserisco casella tee e conteggio flight
    strTable += '<td style="color: black">' + 10;
    strTable +=
      '<td style="background-color:' +
      colorC +
      '; color: black">' +
      (parseInt(i) + parseInt(array1.length) + 1);
    start_time = addtime(start_time, gap);
  }
  // console.log("mod :" + mod)
  // console.log("array1 :" + array1)
  // console.log("array1 length :" + array1.length)
  // console.log("array2 :" + array2)
  // console.log("array2 length :" + array2.length)
};
const simpleU = () => {
  let flights = Math.ceil(players / mod);
  let left_flights = Math.ceil(flights / 2);
  let right_flights = flights - left_flights;
  let limit_1 = 3 * left_flights;
  if (limit_1 % 3 === 1) {
    limit_1 = limit_1 + 1;
  }
  if (limit_1 % 3 === 2) {
    limit_1 = limit_1 + 2;
  }
  limit_1 = limit_1 + 1;

  let first_array = build_array(1, limit_1);
  let second_array = build_array(limit_1, players + 1);
  // if(left_flights != right_flights) second_array.splice(0, 0, ""); // prima riga void

  first_array = first_array.reverse();
  build_rows(first_array, second_array, "none", "none", "none");
  return strTable;
};
const doubleU = () => {
  let flights = Math.ceil(players / mod);
  // lower_flights sono fatti pari e sono quindi un fatto certo
  let lower_flights = Math.ceil(flights / 2);
  if (lower_flights % 2 === 1) lower_flights = lower_flights + 1;
  let upper_flights = flights - lower_flights;
  let left_flights = Math.ceil(upper_flights / 2);
  let right_flights = upper_flights - left_flights;
  // left_flights è un fatto certo
  let limit_1 = 3 * left_flights;
  if (limit_1 % 3 === 1) {
    limit_1 = limit_1 + 1;
  }
  if (limit_1 % 3 === 2) {
    limit_1 = limit_1 + 2;
  }
  // lower_flights sono fatti pari e sono quindi un fatto certo
  let limit_3 = players - (3 * lower_flights) / 2;
  let limit_2 = limit_3 - (3 * lower_flights) / 2;

  let first_array = build_array(1, limit_1 + 1);
  let second_array = build_array(limit_1 + 1, limit_2 + 1);
  let third_array = build_array(limit_2 + 1, limit_3 + 1);
  let forth_array = build_array(limit_3 + 1, players + 1);
  // if(left_flights != right_flights) second_array.splice(0, 0, ""); // prima riga void

  build_rows(first_array, second_array, "none", "none", "none");
  build_rows(third_array, forth_array, "none", "none", "none");
  return strTable;
};
// funzione per determinare quanti sono i flights "late" dai quali parte il conteggio
const lowerFlights = (numero, mod) => {
  // Flights
  let flights = Math.ceil(numero / mod);

  // lower_flights sono costruiti pari e sono quindi un fatto certo.
  // upper_flights sono maggiori come numero e quindi lower floor
  let lower_flights = Math.floor(flights / 2);
  // a seconda dei casi per pareggiare aggiungo o sottraggo
  if (mod == 3) {
    switch (numero % (2 * mod)) {
      case 1:
      case 2:
      case 3:
        if (lower_flights % 2 === 1) lower_flights = lower_flights + 1;
        break;
      case 0:
      case 4:
      case 5:
        if (lower_flights % 2 === 1) lower_flights = lower_flights - 1;
        break;
      default:
        if (lower_flights % 2 === 1) lower_flights = lower_flights - 1;
    }
  }
  if (mod == 4) {
    switch (numero % (4 * mod)) {
      case 1:
      case 2:
      case 3:
      case 4:
        if (lower_flights % 2 === 1) lower_flights = lower_flights + 1;
        break;
      default:
        if (lower_flights % 2 === 1) lower_flights = lower_flights - 1;
    }
  }
  return lower_flights;
};
// definisce i limiti dei quadranti per uomini
const limiti_quadranti = (numero, mod) => {
  // definisco i flights "late" che chiamo "lower_flights"
  let lower_flights = lowerFlights(numero, mod);
  // i quadranti superiori calcolati come differenza tra i
  // quadranti complessivi sottratti i quadranti inferiori.
  // Il quadrante superiore sinistro è il maggiore dei due
  let upper_flights = Math.ceil(numero / mod) - lower_flights;
  let left_flights = Math.ceil(upper_flights / 2);
  let right_flights = upper_flights - left_flights;

  // I limiti indicano i confini dei quadranti:
  limit_1 = (mod * lower_flights) / 2;
  limit_2 = mod * lower_flights;
  limit_3 = mod * lower_flights + mod * left_flights;

  return [limit_1, limit_2, limit_3];
};
// definisce il limite dei quadranti con partenze femminili NON in mezzo
const limiti_quadranti_NT = (players, proette, mod) => {
  // Flights sono complessivi uomini donne
  let flights = Math.ceil(players / mod) + Math.ceil(proette / mod);

  // lower_flights sono costruiti pari e sono quindi un fatto certo.
  let lower_flights = Math.ceil(flights / 2);
  if (lower_flights % 2 === 1) lower_flights = lower_flights + 1;

  // i quadranti superiori uomini come differenza tra i quadranti complessivi uomini
  // sottratti i quadranti inferiori.
  // Il quadrante superiore sinistro è il maggiore dei due
  let upper_flights_men = Math.ceil(players / mod) - lower_flights;
  let left_flights_men = Math.ceil(upper_flights_men / 2);
  let right_flights_men = upper_flights_men - left_flights_men;

  // i quadranti donne sono una semplice U
  // Il quadrante superiore sinistro è il maggiore dei due
  let flights_women = Math.ceil(proette / mod);
  // già definito come global da il riferimento numerico del quadrante in alto a sinistra
  // cioè partenze dalla 1. Ogni compensazione viene fatta sulla 10
  let left_flights_women = Math.ceil(flights_women / 2);
  let right_flights_women = flights_women - left_flights_women;

  // I limiti indicano i confini dei quadranti:
  // limit_1 indica il quadrante sinistro donne che costituiscono una U semplice rovesciata.
  // limit_2 indica il confine della metà superiore sinistra uomini. È quindi dato dalla somma dei
  // giocatori presenti nei quadranti inferiori più quelli presenti nel quadrante superiore sinistro.
  // limit_3 indica il quandrante basso sinistro ed è esattamente metà dei giocatori nel quadrante inferiore.
  // Questo perchè il quadrante inferiore è costruito per essere pari ed uguale nelle sue due metà.
  limit_1 = mod * left_flights_women;
  limit_2 = mod * lower_flights;
  limit_3 = mod * lower_flights + mod * left_flights_men;
};
// Costruisce l'insieme dei quadranti. Se inverso = "inverse" si inverte
// il verso del flight (3-2-1) invece (1-2-3).
// Con reverse si inverte la numerazione in modo da realizzare la "U"
const quadranti_array = (inverso) => {
  // introduco array nominativo o numerico
  set_nominativo();
  var first_array = build_array(1, limit_1, atlete, inverso);
  var second_array = build_array(limit_1 + 1, parseInt(proette), atlete, inverso);
  var third_array = build_array(limit_2 + 1, limit_3, atleti, inverso);
  var forth_array = build_array(limit_3 + 1, parseInt(players), atleti, inverso);
  var fifth_array = build_array(1, limit_2 / 2, atleti, inverso);
  var sixth_array = build_array(limit_2 / 2 + 1, limit_2, atleti, inverso);
  if (inverso !== "inverse") {
    first_array = first_array.reverse();
    forth_array = forth_array.reverse();
    fifth_array = fifth_array.reverse();
  } else {
    second_array = second_array.reverse();
    third_array = third_array.reverse();
    sixth_array = sixth_array.reverse();
  }
  return [first_array, second_array, third_array, forth_array, fifth_array, sixth_array];
};
// calcola i quadranti in base alle informazioni e a seconda della "giornata" di gara
export const tee_doppio = (giornata) => {
  // calcola i limiti dei quadranti con partenze femminili NON in centro
  limiti_quadranti_NT(players, proette, mod);

  var [
    first_array,
    second_array,
    third_array,
    forth_array,
    fifth_array,
    sixth_array
  ] = (gara_NT == "Gara 36 buche" && giornata == "seconda") ?
  quadranti_array("inverse"): quadranti_array();

  if (gara_NT == "Gara 54 buche") {
    if (giornata == "prima") {
      build_rows(first_array, second_array, "red", "transparent", "transparent");
      start_time = addtime(start_time, "00:10");
      build_rows(third_array, forth_array, "black", "#ed7d31", "#c6e0b4");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late")
        start_time = addtime(start_time, halftime(round));
      start_time = addtime(start_time, "00:10");
      build_rows(fifth_array, sixth_array, "black", "#e7e6e6", "#ffff00");
      return strTable;
    }
    if (giornata == "seconda") {
      build_rows(sixth_array, fifth_array, "black", "#e7e6e6", "#ffff00");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late") start_time = addtime(start_time, halftime(round));
      start_time = addtime(start_time, "00:10");
      build_rows(second_array, first_array, "red", "transparent", "transparent");
      start_time = addtime(start_time, "00:10");
      build_rows(forth_array, third_array, "black", "#ed7d31", "#c6e0b4");
      return strTable;
    }
  }
  if (gara_NT == "Gara 36 buche") {
    if (giornata == "prima") {
      build_rows(sixth_array, fifth_array, "black", "#ed7d31", "#c6e0b4");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late") start_time = addtime(start_time, halftime(round));
      start_time = addtime(start_time, "00:10");
      build_rows(second_array, first_array, "red", "none", "none");
      start_time = addtime(start_time, "00:10");
      build_rows(forth_array, third_array, "black", "#e7e6e6", "#ffff00");
      return strTable;
    }
    if (giornata == "seconda") {
      build_rows(first_array, second_array, "red", "none", "none");
      start_time = addtime(start_time, "00:10");
      build_rows(third_array, forth_array, "black", "#e7e6e6", "#ffff00");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late")
        start_time = addtime(start_time, halftime(round));
      start_time = addtime(start_time, "00:10");
      build_rows(fifth_array, sixth_array, "black", "#ed7d31", "#c6e0b4");
      return strTable;
    }
  }
};
// calcola i quadranti in base alle informazioni e a seconda della "giornata" di gara
export const tee_doppio_simmetrico = (giornata) => {
  // introduco array nominativo o numerico
  set_nominativo();
  // calcola i limiti dei quadranti con partenze femminili NON in centro
  let [limit_1, limit_2, limit_3] = limiti_quadranti(players, mod);
  let [limit_1w, limit_2w, limit_3w] = limiti_quadranti(proette, mod);

  let first_array = build_array(1, limit_1, atleti);
  let second_array = build_array(limit_1 + 1, limit_2, atleti);
  let third_array = build_array(1, limit_1w, atlete);
  let forth_array = build_array(limit_1w + 1, limit_2w, atlete);
  let fifth_array = build_array(limit_2w + 1, limit_3w, atlete);
  let sixth_array = build_array(limit_3w + 1, parseInt(proette), atlete);
  let seventh_array = build_array(limit_2 + 1, limit_3, atleti);
  let eighth_array = build_array(limit_3 + 1, parseInt(players), atleti);
  // if(left_flights != right_flights) second_array.splice(0, 0, ""); // prima riga void
  first_array = first_array.reverse();
  third_array = third_array.reverse();
  sixth_array = sixth_array.reverse();
  eighth_array = eighth_array.reverse();

  if (gara_NT == "Gara 54 buche") {
    if (giornata == "prima") {
      build_rows(seventh_array, eighth_array, "black", "#e7e6e6", "#ffff00");
      start_time = addtime(start_time, "00:10");
      build_rows(fifth_array, sixth_array, "red", "#e7e6e6", "#ffff00");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late")
        start_time = addtime(start_time, halftime(round));
      build_rows(third_array, forth_array, "red", "#ed7d31", "#c6e0b4");
      start_time = addtime(start_time, "00:10");
      build_rows(
        second_array,
        first_array,
        "black",
        "transparent",
        "transparent"
      );
      return strTable;
    }
    if (giornata == "seconda") {
      build_rows(
        second_array,
        first_array,
        "black",
        "transparent",
        "transparent"
      );
      start_time = addtime(start_time, "00:10");
      build_rows(sixth_array, fifth_array, "red", "#e7e6e6", "#ffff00");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late")
        start_time = addtime(start_time, halftime(round));
      build_rows(forth_array, third_array, "red", "#ed7d31", "#c6e0b4");
      start_time = addtime(start_time, "00:10");
      build_rows(eighth_array, seventh_array, "black", "#e7e6e6", "#ffff00");
      return strTable;
    }
  }
  if (gara_NT == "Gara 36 buche") {
    if (giornata == "prima") {
      build_rows(eighth_array, seventh_array, "black", "#e7e6e6", "#ffff00");
      start_time = addtime(start_time, "00:10");
      build_rows(sixth_array, fifth_array, "red", "#e7e6e6", "#ffff00");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late")
        start_time = addtime(start_time, halftime(round));
      build_rows(forth_array, third_array, "red", "#ed7d31", "#c6e0b4");
      start_time = addtime(start_time, "00:10");
      build_rows(
        second_array,
        first_array,
        "black",
        "transparent",
        "transparent"
      );
      return strTable;
    }
    if (giornata == "seconda") {
      build_rows(seventh_array, eighth_array, "black", "#e7e6e6", "#ffff00");
      start_time = addtime(start_time, "00:10");
      build_rows(fifth_array, sixth_array, "red", "#e7e6e6", "#ffff00");
      strTable += "<tr><td>";
      // start_time = addtime("09:50" + round);
      if (compatto == "Early/Late")
        start_time = addtime(start_time, halftime(round));
      build_rows(third_array, forth_array, "red", "#ed7d31", "#c6e0b4");
      start_time = addtime(start_time, "00:10");
      build_rows(
        first_array,
        second_array,
        "black",
        "transparent",
        "transparent"
      );
      return strTable;
    }
  }
};
// divide il field per creare le partenze da un solo tee
export const tee_unico = (simmetrico, giornata) => {
  console.log(JSON.stringify(simmetrico, null, 4))
  console.log(JSON.stringify(giornata, null, 4))
  // Flights sono complessivi uomini donne
  let flights = Math.ceil(players / mod) + Math.ceil(proette / mod);

  // Flights uomini della "mattina" con donne in mezzo
  // lower_flights sono costruiti pari e sono quindi un fatto certo.
  let upper_flights_men = Math.ceil(players / mod / 2);
  let lower_flights_men =
    Math.ceil(players / mod) - Math.ceil(players / mod / 2);

  // i quadranti donne sono una semplice U
  // Il quadrante superiore sinistro è il maggiore dei due
  let flights_women = Math.ceil(proette / mod);

  // l'unico limite è quello di separazione tra uomini "mattina" e donne nel caso di donne in mezzo
  // quindi è legato al numero di uomini "late" che deve essere pari
  let limit = mod * lower_flights_men;

  // introduco array nominativo o numerico
  set_nominativo();
  // first_array parte dall'ultimo fino a "limit" fissato prima
  // second_array in questo caso sono tutte le donne
  // third_array parte dal "limit" fino ai primi
  // base è il contatore per riprendere il conteggio dei flights dopo le donne
  let first_array = build_array(
    limit + 1,
    parseInt(players),
    atleti,
    "inverse"
  );
  let second_array = build_array(1, parseInt(proette), atlete, "inverse");
  let third_array = build_array(1, limit, atleti, "inverse");
  let base;
  let primo_array = first_array;
  let secondo_array = second_array;
  let rossoSimmetrico = 'style="font-style:italic; color:red"';
  let rossoAsimmetrico = '';

  if (simmetrico == "Asimmetrico") {
    primo_array = second_array;
    secondo_array = first_array;
    rossoAsimmetrico = 'style="font-style:italic; color:red"';
    rossoSimmetrico = '';
  }
  if (giornata == "prima") {
    for (let i = 0; i < primo_array.length; i++) {
      $.each(primo_array[i], function(index, value) {
        if (index == 0)
          strTable +=
          '<tr ' + rossoAsimmetrico + '><td>' +
          parseInt(i + 1) +
          "<td>" +
          1;
        strTable += "<td>" + value;
      });
      strTable += "<td>" + start_time;
      start_time = addtime(start_time, gap);
      base = i + 1;
    }
    start_time = addtime(start_time, "00:10");
    for (let i = 0; i < secondo_array.length; i++) {
      $.each(secondo_array[i], function(index, value) {
        if (index == 0)
          strTable +=
          '<tr ' + rossoSimmetrico + '><td>' +
          parseInt(i + 1) +
          "<td>" +
          1;
        strTable += "<td>" + value;
      });
      strTable += "<td>" + start_time;
      start_time = addtime(start_time, gap);
    }
    start_time = addtime(start_time, "00:10");
    for (let i = 0; i < third_array.length; i++) {
      $.each(third_array[i], function(index, value) {
        if (index == 0)
          strTable += "<tr><td>" + parseInt(base + i + 1) + "<td>" + 1;
        strTable += "<td>" + value;
      });
      strTable += "<td>" + start_time;
      start_time = addtime(start_time, gap);
    }

    return strTable;
  }
  if (giornata == "seconda") {
    for (let i = 0; i < third_array.length; i++) {
      $.each(third_array[i], function(index, value) {
        if (index == 0)
          strTable +=
          '<tr ' + rossoSimmetrico + '><td>' +
          parseInt(i + 1) +
          "<td>" +
          1;
        strTable += "<td>" + value;
      });
      strTable += "<td>" + start_time;
      start_time = addtime(start_time, gap);
      base = i + 1;
    }
    start_time = addtime(start_time, "00:10");
    for (let i = 0; i < secondo_array.length; i++) {
      $.each(secondo_array[i], function(index, value) {
        if (index == 0)
          strTable +=
          '<tr ' + rossoSimmetrico + '><td>' +
          parseInt(i + 1) +
          "<td>" +
          1;
        strTable += "<td>" + value;
      });
      strTable += "<td>" + start_time;
      start_time = addtime(start_time, gap);
    }
    start_time = addtime(start_time, "00:10");
    for (let i = 0; i < primo_array.length; i++) {
      $.each(primo_array[i], function(index, value) {
        if (index == 0)
          strTable +=
          '<tr ' + rossoAsimmetrico + '><td>' +
          parseInt(base + i + 1) +
          '<td>' +
          1;
        strTable += '<td>' + value;
      });
      strTable += '<td>' + start_time;
      start_time = addtime(start_time, gap);
    }

    return strTable;
  }
};
// blocco interno timing sheet
const timingSheetBody = (start, end) => {
  // clock ora di partenza del flight
  clock = start_time;
  // inizializzo il contatore di riga del tempo
  let progressiveClock = "";
  strTable += "<tbody>";
  // eseguo il loop sui flight
  for (let i = start; i <= end; i++) {
    // azzero il contatore di riga
    let progressiveTime = 0;
    strTable += '<tr style="border-top: 2px solid">';
    // inserisco due righe vuote a metà
    strTable +=
      i == halfFlightEarly || i == halfFlightLate ?
      "<td colspan=21>&nbsp;<tr>" :
      "";
    strTable +=
      i == halfFlightEarly || i == halfFlightLate ?
      '<td colspan=21>&nbsp;<tr style="border-top: 2px solid">' :
      "";
    // Flight
    strTable += '<td style="border-left: 2px solid">' + i;
    // Tee
    strTable +=
      isBetween(i, halfFlightEarly, flightsEarly) ||
      isBetween(i, halfFlightLate, flights) ?
      "<td>" + 10 :
      "<td>" + 1;
    // clock ora di partenza viene incrementato del valore del gap eccetto che per i = 1
    // e per halfFlight e conseguentemente per flightsEarly+1 e halfFlightLate
    clock =
      i === 1 ||
      i == halfFlightEarly ||
      i == halfFlightLate ||
      i == flightsEarly + 1 ?
      start_time :
      addtime(clock, gap);
    strTable += '<td style="border-right: 2px solid">' + clock;
    // loop di riga da buca 1 a buca 18
    $.each(timeArray, function(index, value) {
      let border =
        (index + 1) % 3 == 0 ? "style='border-right: 2px solid'" : "";
      // valore numerico incrementale sulla base dei tempi per buca
      progressiveTime += parseInt(value);
      // trasformo il valore ottenuto in format hh:mm
      progressiveClock = formatMinutes(progressiveTime);
      // aggiungo l'ora di partenza e il valore del gap eccetto che per i = 1
      progressiveClock = addtime(progressiveClock, clock, gap);
      strTable += "<td " + border + ">" + progressiveClock;
    });
  }
  // fisso lo start_time a seguire per una second table
  start_time = addtime(clock, halfround, gap);
  return strTable;
};
// genera il timing sheet come tabella
export function timing_sheet(start, end) {
  strTable = "<table>";
  let strTableHead = '<thead style="text-align:center; border: 2px solid"><th>Team<th>Tee<th style="border-right: 2px solid">Ora';
  let strTableFoot = '<tfoot style="text-align:center; border: 2px solid"><th>Team<th>Tee<th style="border-right: 2px solid">Ora';
  let totalpar = 0;
  let totaltime = 0;
  // creo timeArray e parArray
  parList =
    localStorage.getItem("parList") ??
    (4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4);
  parArray = parList
    .split(",")
    .map((item) => (parseInt(item) ? parseInt(item) : item));
  timeList =
    localStorage.getItem("timeList") ??
    (15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15);
  timeArray = timeList
    .split(",")
    .map((item) => (parseInt(item) ? parseInt(item) : item));
  for (let i = 1; i <= 18; i++) {
    let border = i % 3 == 0 ? "style='border-right: 2px solid'" : "";
    let parId = "parId" + i;
    let parValue = "parValue" + i;
    parValue = parseInt(parArray[i - 1]);
    // inserisco valori di default
    $("#" + parId).val(parValue);

    let timeId = "timeId" + i;
    let timeValue = "timeValue" + i;
    timeValue = parseInt(timeArray[i - 1]);
    // inserisco valori di default
    $("#" + timeId).val(timeValue);

    localStorage.setItem("parList", parList);
    localStorage.setItem("timeList", timeList);

    totalpar += parseInt(parValue);
    totaltime += parseInt(timeValue);
    strTableHead += "<th " + border + ">" + i;
    strTableFoot += "<th " + border + ">" + i;
  }
  round = formatMinutes(totaltime);
  $("#totalpar").html(totalpar);
  $("#totaltime").html(formatMinutes(totaltime));

  strTable += strTableHead;

  timingSheetBody(start, end);

  strTable += strTableFoot;
  return strTable;
}

export function showTable() {
  // in base ai conteggi genero le tabelle
  flights = Math.floor(players / mod);
  if (flights > 24) {
    flightsEarly = Math.floor(flights / 2);
    flightsLate = flights - flightsEarly;
    halfFlightEarly = Math.floor(flightsEarly / 2) + 1;
    halfFlightLate = flightsEarly + Math.floor(flightsLate / 2) + 1;

    strTable = timing_sheet(1, flightsEarly);
    $("#first_table").html(strTable);
    strTable = timing_sheet(flightsEarly + 1, flights);
    $("#second_table").html(strTable);
  } else {
    flightsEarly = flights;
    flightsLate = flights;
    halfFlightEarly = Math.floor(flights / 2) + 1;
    halfFlightLate = Math.floor(flights / 2) + 1;
    strTable = timing_sheet(1, flights);
    $("#first_table").html(strTable);
  }
  round = formatMinutes(totaltime);
}