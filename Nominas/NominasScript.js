const SSID = SpreadsheetApp.getActiveSpreadsheet().getId();
const TABLAS_SHEET = "SOLICITUD_NOMINA";
const PLANTILLA_NAME = `Plantilla_Tablas`;

const SSID_A2 = `18OWh6mXY0-o5MRCAZEMQrznQmMJURt9n5bnolfF8ERU`; // Archivo A2 PRUEBA
const ID_DIRECTORIO = `1NZBsJOLjnP6aojinaPUaLMnliDHYnNqVJKMYq8VhTJE`; // V0.2
const ID_MASTER_GASTOS = `178M33EaTbv6rT6CA2XkA_csJlMoBI9Ej3s1T_7hq0no`;

function onOpen() {
  var ui = SpreadsheetApp.getUi();
  var menu = ui.createMenu("NOMINA");
  menu.addSeparator();
  // menu.addItem("Generar Nomina Anual","generarNominaAnual");
  // menu.addItem("➕ Agregar Colaborador","agregarColaborador");
  // menu.addItem("➖ Quitar Colaborador","quitarColaborador");
  menu.addSeparator();
  menu.addItem("⏳ Horas Extra","horasExtra");
  menu.addSeparator();
  // menu.addItem("Comisiones","comisiones");
  menu.addSeparator();
  menu.addToUi();
}

function tablaNomina() {
  const regla = SpreadsheetApp.newDataValidation()
    .requireCheckbox("TRUE", "FALSE")
    .build();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const PLANTILLA = ss.getSheetByName(PLANTILLA_NAME);
  const SHEET = ss.getSheetByName(TABLAS_SHEET);

  var totalRange = (SHEET.getRange(1001,11,900,6).getValues().slice(1)).filter(fila =>
    fila[0] != "" && 
    fila[0] != null && 
    fila[0] != `USUARIO FINAL`);

  var nomInfo = totalRange.filter(fila => 
    fila[5] == `NOMINA SEMANAL` &&
    fila[2] != 0).map(fila => [fila[0], fila[2], fila[1]]);

  totalRange = totalRange.map(fila => [fila[0], fila[5], fila[2]]);

  var afiInfo = totalRange.filter(fila => 
    (fila[1] == `IMPUESTO SOBRE LA NOMINA` || 
    fila[1] == `AGUINALDOS`) &&
    fila[2] != 0);

  var bonoInfo = totalRange.filter(fila => 
    (fila[1] == `BONO LEALTAD` || 
    fila[1] == `BONO DESPENSA` || 
    fila[1] == `BONO TRANSPORTE`) &&
    fila[2] != 0);
     
  var empInfo = totalRange.filter(fila => 
    fila[1] == `BONO GRATIFICACION` &&
    fila[2] != 0);

  var kpiInfo = totalRange.filter(fila => 
    fila[1] == `BONO MENSUAL` &&
    fila[2] != 0);

  var infoInfo = totalRange.filter(fila => 
    fila[1] == `CREDITO INFONAVIT`).map(fila =>
    [fila[2]]);

  // Validacion para Bonos segun semana
  var diasBono = 22; // 22 Cuarto Viernes para Bonos Bienestar 🟢
  // var diasBono = 1; // 1 Primer Viernes (PRUEBA)
  (getFridayOfMonth(diasBono))?afiInfo = [...afiInfo, ...bonoInfo]:0;

  var diasBono = 8; // 8 Segundo Viernes para Bono KPIs 🔴
  // var diasBono = 1; // 1 Primer Viernes (PRUEBA)
  (getFridayOfMonth(diasBono))?afiInfo = [...afiInfo, ...kpiInfo]:0;

  var diasBono = 15; // 15 Tercer Viernes para Bono Empleado del Mes 🟡
  // var diasBono = 1; // 1 Primer Viernes (PRUEBA)
  (getFridayOfMonth(diasBono))?afiInfo = [...afiInfo, ...empInfo]:0;

  //  afiInfo contiene todos los datos que se muestran en la segunda tabla.
  //  V2 no se muestran datos de Aguinaldos ni Impuesto sobre la Nomina
  //  Incluir tabla aparte de Horas Extras

  const plantEncabezados2 = PLANTILLA.getRange(1,1,7,16);
  const plantNomFormatRange = PLANTILLA.getRange(8,1,nomInfo.length,5);
  const avisoRange = PLANTILLA.getRange(`M34:P39`);
  avisoRange.copyFormatToRange(SHEET, 13, 16, 39, 44);
  SHEET.getRange(`M39`).setValue(avisoRange.getValue());
  plantEncabezados2.copyFormatToRange(SHEET, 1, 11, 6, 12);
  SHEET.getRange(`A6:P12`).setValues(plantEncabezados2.getValues());
  plantNomFormatRange.copyFormatToRange(SHEET, 1, 5, 13, nomInfo.length+12);

  SHEET.getRange(`A7`).setFormula(PLANTILLA.getRange("A2").getFormula());
  SHEET.getRange(`G7`).setFormula(PLANTILLA.getRange("G2").getFormula());  
  SHEET.getRange(13,5,nomInfo.length,1).setFormulas(PLANTILLA.getRange(8,5,nomInfo.length,1).getFormulas());
  // SHEET.getRange(13,4,nomInfo.length,1).setValues(PLANTILLA.getRange(8,4,nomInfo.length,1).getValues());
  (infoInfo.length>0)?SHEET.getRange(13,4,infoInfo.length,1).setValues(infoInfo):0;
  SHEET.getRange(13,1,nomInfo.length,3).setValues(nomInfo);

  if(afiInfo.length>0){
    const plantAllFormatRange = PLANTILLA.getRange(8,7,afiInfo.length,5);
    plantAllFormatRange.copyFormatToRange(SHEET, 7, 11, 13, afiInfo.length+12);
    SHEET.getRange(13,11,afiInfo.length,1).setFormulas(PLANTILLA.getRange(8,11,afiInfo.length,1).getFormulas());
    // SHEET.getRange(13+afiInfo.filter(fila => fila[1] == `AGUINALDOS` || fila[1] == `IMPUESTO SOBRE LA NOMINA`).length,10,afiInfo.length,1).setDataValidation(regla);
    try {
      const offset = afiInfo.filter(
        fila => fila[1] === 'AGUINALDOS' || fila[1] === 'IMPUESTO SOBRE LA NOMINA'
      ).length;
      const startRow = 13 + offset;
      const numRows = afiInfo.filter(
        fila => fila[1] != 'AGUINALDOS' && fila[1] != 'IMPUESTO SOBRE LA NOMINA'
      ).length;
      if (numRows > 0) {
        SHEET
          .getRange(startRow, 10, numRows, 1)
          .setDataValidation(regla);
      }
    } catch (err) {
      Logger.log('Error al aplicar validación: ' + err.message);
      SpreadsheetApp.getActiveSpreadsheet().toast('Error al aplicar validación: ' + err.message)
    }
    SHEET.getRange(13,7,afiInfo.length,3).setValues(afiInfo);
    (SHEET.getRange(afiInfo.length+12,8,1,1).getValue()==`BONO GRATIFICACION`)?SHEET.getRange(afiInfo.length+12,7,1,1)
      .setDataValidation(SHEET.getRange(`K1402`).getDataValidation()):0;
    return
  }
  SHEET.getRange(`G10:K12`).clear();
}

//////////////////////////////

// Función actualizada para cuarto viernes del mes (day = 5)
function getFridayOfMonth(diasBono) {
// function getFridayOfMonth() {
  // var diasBono = 15;
  var hoja = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(TABLAS_SHEET);
  if(hoja.getRange(`R5`).getValue()){
    var pruebaFecha = hoja.getRange(`R2`).getValue();
    var year = (new Date(pruebaFecha)).getFullYear();
    var month = (new Date(pruebaFecha)).getMonth();
    var date = (new Date(pruebaFecha)).getDate();
    const fF = new Date(year, month, diasBono); // 0-Primera, 7-Segunda, 14-Tercera, 21-Cuarta
    const thisFriday = new Date(year, month, date+2);
    while (fF.getDay() !== 5) fF.setDate(fF.getDate() + 1); // 5 = viernes
    // console.log(`${getWeekNumber(fF)} - ${getWeekNumber(thisFriday)}`);
    return getWeekNumber(fF)==getWeekNumber(thisFriday);
  }

  var year = (new Date()).getFullYear();
  var month = (new Date()).getMonth();
  var date = (new Date()).getDate();
  const fF = new Date(year, month, diasBono); // 0-Primera, 7-Segunda, 14-Tercera, 21-Cuarta
  const thisFriday = new Date(year, month, date+2);
  while (fF.getDay() !== 5) fF.setDate(fF.getDate() + 1); // 5 = viernes
  // console.log(getWeekNumber(fF)==getWeekNumber(thisFriday));
  return getWeekNumber(fF)==getWeekNumber(thisFriday);
}

//////////////////////////////

function resetAfi(){
  var hoja = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(TABLAS_SHEET);
  const nombres = hoja.getRange("K1602:K1700").getValues().flat().filter(String);
  var values = Array(nombres.length).fill([0]);
  hoja.getRange(1602, 13, nombres.length, 1).setValues(values);
  hoja.getRange(1702, 13, nombres.length, 1).setValues(values);
  hoja.getRange(1802, 13, nombres.length, 1).setValues(values);
}

//////////////////////////////

function getWeekNumber(date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const dayNum = d.getUTCDay() || 5; // Viernes = 5
  d.setUTCDate(d.getUTCDate() + 4 - dayNum);
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  const weekNum = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
  return weekNum;
}

//////////////////////////////

function probarSemana() {
  var fecha = new Date("2025-09-19"); // ejemplo
  var numSemana = getWeekNumber(fecha);
  Logger.log("Semana ISO: " + numSemana);
}

//////////////////////////////

function delTable() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const SHEET = ss.getSheetByName(TABLAS_SHEET);

  const nomRange = SHEET.getRange("A4:P1000");
  nomRange.clearContent().clearFormat().clearDataValidations();
}

//////////////////////////////

function dataValidation(){
  const regla = SpreadsheetApp.newDataValidation()
    .requireCheckbox("TRUE", "FALSE")
    .build();
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(PLANTILLA_NAME);
    var range = sheet.getRange("J8");
    range.setDataValidation(regla);
}


function addColab(data) {
  if(
    data.quienSol == "" || data.quienSol == null ||
    data.dptoSol == "" || data.dptoSol == null ||
    data.dondeAplica == "" || data.dondeAplica == null ||
    data.name == "" || data.name == null ||
    data.sueldoBase == "" || data.sueldoBase == null ||
    data.fechaIngreso == "" || data.fechaIngreso == null
  ){
      var htmlIncompleto = HtmlService.createHtmlOutput(`
    <html>
      <body style="
        text-align:center;
        margin:0;
        padding:20px;
        background:white;
        font-family:Arial, sans-serif;
      ">
        <h3>Debe llenar todos los campos!</h3>
        <p>Favor de intentarlo de nuevo.</p>
        <button 
          style="
            margin-top:15px;
            background-color:#1a73e8;
            color:white;
            border:none;
            padding:8px 16px;
            border-radius:6px;
            cursor:pointer;
            font-size:14px;
          "
          onclick="google.script.host.close()"
        >
          Aceptar
        </button>
      </body>
    </html>
  `).setWidth(300)
  .setHeight(210);
  SpreadsheetApp.getUi().showModalDialog(htmlIncompleto, 'Datos Incompletos!');
  SpreadsheetApp.getUi().alert(JSON.stringify(data));
    return
  }
  SpreadsheetApp.getActiveSpreadsheet().toast(`Agregando...`);
  var tablasSheet = SpreadsheetApp.openById(SSID).getSheetByName(TABLAS_SHEET);
  var listaSheet = SpreadsheetApp.openById(SSID).getSheetByName("Colaboradores");
  var semanaLastRow = (tablasSheet.getRange(1001, 1).getDataRegion().getLastRow() + 1);
  var lealtadLastRow = (tablasSheet.getRange(1101, 1).getDataRegion().getLastRow() + 1);
  var despensaLastRow = (tablasSheet.getRange(1201, 1).getDataRegion().getLastRow() + 1);
  var transporteLastRow = (tablasSheet.getRange(1301, 1).getDataRegion().getLastRow() + 1);
  var kpiLastRow = (tablasSheet.getRange(1501, 1).getDataRegion().getLastRow() + 1);

  var listaLastRow = (listaSheet.getRange(`G12`).getDataRegion().getLastRow()+1)
  listaSheet.getRange(`G${listaLastRow}`).setValue((data.name).toUpperCase());


  var colabSemArray = [[
    "",
    new Date().toISOString().substring(0,10),
    data.quienSol,
    data.dptoSol,
    "DESPACHO",
    "SEMANAL", // Periodicidad - 5
    data.dondeAplica,
    "NOMINAS",
    "NOMINA", // Subcategoria - 8
    "",
    (data.name).toUpperCase(),
    5, // Cantidad - 11
    (data.sueldoBase/5), // Precio Unitario 12
    "N/A",
    "N/A",
    "NOMINA SEMANAL", // Descripcion - 15
    "SERVICIO",
    "","","","","","",
    `=-(M${semanaLastRow}*L${semanaLastRow})`, // Monto/importe - 23
    "",
    new Date(data.fechaIngreso).toISOString().substring(0,10),
    `=WEEKNUM(TODAY())`,
    `=MONTH(TODAY())`,
    2025,
  ]];

  // BONO LEALTAD
  var colabLealArray = colabSemArray.map(fila => fila.slice());
  colabLealArray[0][5] = "MENSUAL";
  colabLealArray[0][8] = "BONO LEALTAD";
  colabLealArray[0][11] = 1;
  colabLealArray[0][12] = 1000;
  colabLealArray[0][15] = colabLealArray[0][8];
  colabLealArray[0][23] = `=-(M${lealtadLastRow}*L${lealtadLastRow})`;

  // BONO DESPENSA
  var colabDespArray = colabLealArray.map(fila => fila.slice());
  colabDespArray[0][8] = "BONO DESPENSA";
  colabDespArray[0][12] = 400;
  colabDespArray[0][15] = colabDespArray[0][8];
  colabDespArray[0][23] = `=-(M${despensaLastRow}*L${despensaLastRow})`;

  // BONO TRANSPORTE
  var colabTransArray = colabDespArray.map(fila => fila.slice());
  colabTransArray[0][8] = "BONO TRANSPORTE";
  colabTransArray[0][15] = colabTransArray[0][8];
  colabTransArray[0][23] = `=-(M${transporteLastRow}*L${transporteLastRow})`;

  // BONO KPI
  var colabKPIArray = colabDespArray.map(fila => fila.slice());
  colabKPIArray[0][8] = "BONO MENSUAL";
  colabKPIArray[0][12] = 0;
  colabKPIArray[0][15] = colabKPIArray[0][8];
  colabKPIArray[0][23] = `=-(M${kpiLastRow}*L${kpiLastRow})`;

    // NOMINA SEMANAL
  var semanaRange = tablasSheet.getRange(semanaLastRow-1,1,1,29);
  var semanaRange2 = tablasSheet.getRange(semanaLastRow,1,1,29);
  semanaRange.copyFormatToRange(tablasSheet,1,29,semanaLastRow,semanaLastRow);
  semanaRange2.setDataValidations(semanaRange.getDataValidations());
  tablasSheet.getRange(semanaLastRow,1,1,29).setValues(colabSemArray);

    // BONO LEALTAD
  var lealtadRange = tablasSheet.getRange(lealtadLastRow-1,1,1,29);
  var lealtadRange2 = tablasSheet.getRange(lealtadLastRow,1,1,29);
  lealtadRange.copyFormatToRange(tablasSheet,1,29,lealtadLastRow,lealtadLastRow);
  lealtadRange2.setDataValidations(semanaRange.getDataValidations());
  tablasSheet.getRange(lealtadLastRow,1,1,29).setValues(colabLealArray);

    // BONO DESPENSA
  var despRange = tablasSheet.getRange(despensaLastRow-1,1,1,29);
  var despRange2 = tablasSheet.getRange(despensaLastRow,1,1,29);
  despRange.copyFormatToRange(tablasSheet,1,29,despensaLastRow,despensaLastRow);
  despRange2.setDataValidations(semanaRange.getDataValidations());
  tablasSheet.getRange(despensaLastRow,1,1,29).setValues(colabDespArray);

    // BONO TRANSPORTE
  var transRange = tablasSheet.getRange(transporteLastRow-1,1,1,29);
  var transRange2 = tablasSheet.getRange(transporteLastRow,1,1,29);
  transRange.copyFormatToRange(tablasSheet,1,29,transporteLastRow,transporteLastRow);
  transRange2.setDataValidations(semanaRange.getDataValidations());
  tablasSheet.getRange(transporteLastRow,1,1,29).setValues(colabTransArray);

    // BONO PRODUCTIVIDAD
  var kpiRange = tablasSheet.getRange(kpiLastRow-1,1,1,29);
  var kpiRange2 = tablasSheet.getRange(kpiLastRow,1,1,29);
  kpiRange.copyFormatToRange(tablasSheet,1,29,kpiLastRow,kpiLastRow);
  kpiRange2.setDataValidations(semanaRange.getDataValidations());
  tablasSheet.getRange(kpiLastRow,1,1,29).setValues(colabKPIArray);

  var html = HtmlService.createHtmlOutput(`
    <html>
      <body style="
        text-align:center;
        margin:0;
        padding:20px;
        background:white;
        font-family:Arial, sans-serif;
      ">
        <h3>Se ha Agregado Correctamente a:</h3>
        <p>${(data.name).toUpperCase()}</p>
        <button 
          style="
            margin-top:15px;
            background-color:#1a73e8;
            color:white;
            border:none;
            padding:8px 16px;
            border-radius:6px;
            cursor:pointer;
            font-size:14px;
          "
          onclick="google.script.host.close()"
        >
          Aceptar
        </button>
      </body>
    </html>
  `).setWidth(300)
  .setHeight(210);
  SpreadsheetApp.getUi().showModalDialog(html, 'Agregar Colaborador');
  return colabSemArray;
}

//////////////////////////////

function removeColab(colaborador){
  SpreadsheetApp.getActiveSpreadsheet().toast(`Quitando...`);
  const tablasHoja = SpreadsheetApp.openById(SSID).getSheetByName(TABLAS_SHEET);
  // const colaboradoresHoja = SpreadsheetApp.openById(SSID).getSheetByName("Colaboradores");
  const nombreColab = (colaborador.name);

  const colaboradores = tablasHoja.getRange("K:K").getValues().flat();
  for (let i = colaboradores.length - 1; i >= 0; i--) {
    if (colaboradores[i] === nombreColab) {
      tablasHoja.deleteRow(i + 1);
      tablasHoja.insertRowAfter(tablasHoja.getRange(i,11).getDataRegion().getLastRow()+1);
      console.log(`Fila ${i + 1} eliminada (Nombre: ${nombreColab})`);
    }
  }

  // const colaboradoresNombres = colaboradoresHoja.getRange("G:G").getValues().flat();
  // for (let i = colaboradoresNombres.length - 1; i >= 0; i--) {
  //   if (colaboradoresNombres[i] === nombreColab) {
  //     colaboradoresHoja.deleteRow(i + 1);
  //     colaboradoresHoja.insertRowAfter(colaboradoresHoja.getRange(i,11).getDataRegion().getLastRow());
  //     console.log(`Fila ${i + 1} eliminada (Nombre: ${nombreColab})`);
  //   }
  // }
  // pollo(nombreColab);
}

//////////////////////////////

function pollo(nombre){
// function pollo(){
//   var nombre = "POLLO LOCO";
  var html = HtmlService.createHtmlOutput(`<html>
      <body style="
        text-align:center;
        margin:0;
        padding:20px;
        background:white;
        font-family:Arial, sans-serif;
      ">
        <img 
          src="https://static.vecteezy.com/system/resources/previews/020/952/293/non_2x/chicken-hen-standing-free-png.png"
          style="max-width:200px; height:auto;"
        >
        <h3>🚨 ¡Pollo Alert! 🚨</h3>
        <p>Esta es tu alerta del pollo.</p>
        <p>Se ha dado de baja correctamente a:</p>
        <p>${nombre}</p>
        <button 
          style="
            margin-top:15px;
            background-color:#1a73e8;
            color:white;
            border:none;
            padding:8px 16px;
            border-radius:6px;
            cursor:pointer;
            font-size:14px;
          "
          onclick="google.script.host.close()"
        >
          Aceptar
        </button>
      </body>
    </html>`)
  .setWidth(350)
  .setHeight(450);
  SpreadsheetApp.getUi().showModalDialog(html, '🚨 Pollo Alert 🚨');
}

//////////////////////////////

function polloAlert(){
  var html = HtmlService.createHtmlOutput(`<html>
      <body style="
        text-align:center;
        margin:0;
        padding:20px;
        background:white;
        font-family:Arial, sans-serif;
      ">
        <img 
          src="https://static.vecteezy.com/system/resources/previews/020/952/293/non_2x/chicken-hen-standing-free-png.png"
          style="max-width:200px; height:auto;"
        >
        <h3>🚨 ¡Pollo Alert! 🚨</h3>
        <p>Esta es tu alerta del pollo.</p>
        <button 
          style="
            margin-top:15px;
            background-color:#1a73e8;
            color:white;
            border:none;
            padding:8px 16px;
            border-radius:6px;
            cursor:pointer;
            font-size:14px;
          "
          onclick="google.script.host.close()"
        >
          Aceptar
        </button>
      </body>
    </html>`)
  .setWidth(350)
  .setHeight(450);
  SpreadsheetApp.getUi().showModalDialog(html, '🚨 Pollo Alert 🚨');
}

//////////////////////////////

function doGet(e){
  var template = HtmlService.createTemplateFromFile("modalColab");
  var html = template.evaluate().setSandboxMode(HtmlService.SandboxMode.IFRAME);
  return html;
}

//////////////////////////////

function agregarColaborador (){
  openDialog('modalColab', 'Agregar Nuevo Colaborador');
}

//////////////////////////////

function quitarColaborador (){
  openDialog('quitarColab', 'Quitar Colaborador');
}

//////////////////////////////

function horasExtra (){
  openDialog('horasXtra','Horas Extra');
}

//////////////////////////////

function comisiones (){
  openDialog('coms','Comisiones');
}

//////////////////////////////

function obtenerNombres(){
  const hoja = SpreadsheetApp.openById(SSID).getSheetByName(TABLAS_SHEET);

  const lista = hoja.getRange("K1002:K1100").getDisplayValues();
  Logger.log(lista);
  const nombres = lista.flat().filter(String);
  Logger.log(nombres);
  return nombres;
}

//////////////////////////////

function obtenerNombresAgregar(){
  const hoja = SpreadsheetApp.openById(SSID).getSheetByName("Colaboradores");
  const nombres = hoja.getRange("G13:G").getValues().flat().filter(String);
  return nombres;
}

//////////////////////////////

// Opens the html file corresponding to the selected option
function openDialog(temp,title) {
  // var html = HtmlService.createHtmlOutputFromFile(temp);
  var template = HtmlService.createTemplateFromFile(temp);
  var html = template.evaluate().setTitle(title).setHeight(350);
  SpreadsheetApp.getUi()
  .showModalDialog(html, title);
}

//////////////////////////////

function permisos(){
  SpreadsheetApp.getActiveSpreadsheet();
  DriveApp.getRootFolder();
  console.log("Permisos verificados correctamente");
}

function mandarSolicitudBoton(){
  if(mandarSolicitud()){
    rebajes();
    delTable();
    return
  }
  
}

function mandarSolicitud() {
  var nominaHoja = SpreadsheetApp.openById(SSID).getSheetByName(TABLAS_SHEET);
  var nominaSupCompleta = nominaHoja.getRange(12,1,200,16).getValues();
  if (nominaHoja.getRange(`A13`).getValue() == `` || nominaHoja.getRange(`A13`).getValue() == null){
    SpreadsheetApp.getActiveSpreadsheet().toast(`🔴NO HAY DATOS POR MANDAR.🔴`);
    return false
  }
  if ((nominaHoja.getRange(`G13:G300`).getValues().filter(fila => fila[0] === `NOMBRE`)).flat().length === 1 ){
    SpreadsheetApp.getActiveSpreadsheet().toast(`🟡SELECCIONA UN EMPLEADO DEL MES.🟡`);
    return false
  }
  var fechaDinamica = new Date();

  (nominaHoja.getRange(`R5`).getValue())?fechaDinamica = nominaHoja.getRange(`R2`).getValue():0;

  var nominaSemanal = (nominaSupCompleta).filter(fila => 
    fila[0] !== "" && fila[0] !== null && fila[3] !== `HORAS EXTRAS`)
    .map(fila => 
      [fila[0], fila[1], fila[2], `NOMINA`, fila[4]] 
    ).slice(1).filter(fila => 
    fila[0] !== "" && fila[0] !== null && fila[3] !== `HORAS EXTRAS`);
  
  var horasExtra = (nominaSupCompleta).slice(1).filter(fila =>   //  Cambiar el intervalo a la tercer tabla
    fila[12] !== "" && fila[12] !== null && fila[12] !== `AL DARLE CLICK A "MANDAR SOLICITUD" ESTA CONFIRMANDO QUE LOS DATOS SON CORRECTOS.`)
    .map(fila => 
      [fila[12], fila[13], fila[14], `HORAS EXTRAS`, fila[15]] 
    );

  var nominaBonos = (nominaSupCompleta)
    .map(fila => 
      [fila[6],fila[8],1,fila[7], fila[10]]
    ).slice(1).filter(fila => 
    fila[0] !== "" && fila[0] !== null && fila[4] !== 0);

  var solicitudSuperior = [...nominaSemanal, ...horasExtra, ...nominaBonos];

  var datos = nominaHoja.getRange(1002,1,95,29).getValues()
    .filter(fila => fila[10] != `` && fila[10] != null);
  var datosObj = personaObject(datos);
  var today = Utilities.formatDate(fechaDinamica,Session.getScriptTimeZone(), `dd/MM/yyyy`);

  // Logger.log(JSON.stringify(datosObj));
  // return;
  const a2Sheet = SpreadsheetApp.openById(SSID_A2).getSheetByName(`S.Gastos CICLICOS INTERNO PS A2`);
  var consecutivo = a2Sheet.getRange(`A6:A`).getValues();
    // .filter(fila => typeof fila[0] === `string` && fila[0].startsWith(`${numArea}-${archivo}-${numEmpleado}-${numSubcatego}`)).flat()).length+1;

  //   var datosObjStr = JSON.stringify(datosObj);
  // Logger.log(`
  // ${datosObjStr}
  // `);
  // return false

  solicitudSuperior = solicitudSuperior.map(fila => [
    generarIdentificador( // IDENTIFICADOR
      datosObj[fila[0]].AREA_APLICA,
      datosObj[fila[0]].CATEGORIA,
      fila[3],
      fila[0],
      consecutivo) || `SIN DATOS`,
    today, // FECHA CAPTURA
    datosObj[fila[0]].QUIEN_SOL || `SIN DATOS`, // QUIEN SOLICITA
    datosObj[fila[0]].DPTO_SOL || `SIN DATOS`, // DPTO SOLICITANTE
    datosObj[fila[0]].USO || `SIN DATOS`, // USO
    ((fila[3]==`NOMINA`)?`SEMANAL`:(fila[3]==`HORAS EXTRAS`)?`ÚNICO`:`MENSUAL`) || `SIN DATOS`, // PERIODICIDAD
    datosObj[fila[0]].AREA_APLICA || `SIN DATOS`, // ÁREA DONDE APLICA
    datosObj[fila[0]].CATEGORIA || `SIN DATOS`, // CATEGORIA
    fila[3] || `SIN DATOS`, // SUBCATEGORIA
    (fila[3] == `BONO GRATIFICACION`)?`EMPLEADO DEL MES`:(datosObj[fila[0]].DETALLE || `SIN DATOS`), // DETALLE
    fila[0] || `SIN DATOS`, // USUARIO FINAL
    fila[2] || `SIN DATOS`, // CANTIDAD
    (-1*fila[1]) || `SIN DATOS`, // PRECIO UNITARIO
    `N/A`, // MARCA
    `N/A`, // PROVEEDOR
    (fila[3]!=`NOMINA`&&fila[3]!=`HORAS EXTRAS`)?mesNomina(fila[3],today):semanaDelMesNominaSemanal(fila[3],today) || `SIN DATOS`, // DESCRIPCION
    `SERVICIO`, // CATEGORÍA GASTOS
    `TRANSFERENCIA`, // FORMA DE PAGO
    `NACIONAL`, // DETALLE DE PAGO
    `N/A`, // COMENTARIOS DE ENTREGA
    datosObj[fila[0]].DESTINO || `SIN DATOS`, // DESTINO
    datosObj[fila[0]].CUENTA_CLABE || `SIN DATOS`, // CUENTA_CLABE
    datosObj[fila[0]].TITULAR || `SIN DATOS`, // TITULAR
    (-1*fila[4]) || `SIN DATOS`, // MONTO / IMPORTE
    `AZAEL_RANGEL`,
    `N/A`,
    `SIN TICKET`,
    `N/A`
  ])
  

  var sheet13 = SpreadsheetApp.openById(SSID_A2).getSheetByName(`S.Gastos CICLICOS INTERNO PS A2`);
  var lastRow13 = (sheet13.getRange(`C1:C`).getValues().filter(fila => fila[0]!="").flat()).length+3;

  // Logger.log(`Arreglo concat:
  // ${solicitudSuperior}`);

    //  Insertar datos en A2
  sheet13.getRange(lastRow13,1,solicitudSuperior.length,solicitudSuperior[0].length).setValues(solicitudSuperior);
  // return false
  return true
}


function semanaDelMesNominaSemanal(subcatego,fechaStr) {
  const [dia, mes, anio] = fechaStr.split('/').map(n => parseInt(n, 10));
  const fecha = new Date(anio, mes - 1, dia);
  const PRIMER_DIA_SEMANA = 5;
  function obtenerViernesSemana(fecha) {
    const d = new Date(fecha);
    const day = d.getDay();
    const diff = (PRIMER_DIA_SEMANA - day + 7) % 7;
    d.setDate(d.getDate() + diff);
    return d;
  }
  const viernesSemana = obtenerViernesSemana(fecha);
  const mesViernes = viernesSemana.getMonth();
  const anioViernes = viernesSemana.getFullYear();
  const inicioMes = new Date(anioViernes, mesViernes, 1);
  const offset = (inicioMes.getDay() - PRIMER_DIA_SEMANA + 7) % 7;
  const numeroDia = viernesSemana.getDate();
  const semana = Math.floor((numeroDia + offset - 1) / 7);
  var numSemana = ``;
  switch (semana){
    case 1: numSemana = `1RA`; break;
    case 2: numSemana = `2DA`; break;
    case 3: numSemana = `3RA`; break;
    case 4: numSemana = `4TA`; break;
    default: numSemana = `5TA`; break;
  }
  const meses = [
    "ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO",
    "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"
  ];

  var mesNombre = meses[mesViernes] || ``;
  return (`${subcatego} ${numSemana} SEMANA ${mesNombre} ${anioViernes}`);
}

function mesNomina(subcatego,fechaStr) {
  const [dia, mes, anio] = fechaStr.split('/').map(n => parseInt(n, 10));
  const fecha = new Date(anio, mes - 1, dia);
  const PRIMER_DIA_SEMANA = 5;
  function obtenerViernesSemana(fecha) {
    const d = new Date(fecha);
    const day = d.getDay();
    const diff = (PRIMER_DIA_SEMANA - day + 7) % 7;
    d.setDate(d.getDate() + diff);
    return d;
  }
  const viernesSemana = obtenerViernesSemana(fecha);
  const mesViernes = viernesSemana.getMonth();
  const anioViernes = viernesSemana.getFullYear();
  const meses = [
    "ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO",
    "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"
  ];

  var mesNombre = meses[mesViernes] || ``;
  return (`${subcatego} ${mesNombre} ${anioViernes}`);
}

//////////////////////////////

function rebajes(){
  const nominaHoja = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(TABLAS_SHEET);
  var rebajesSemana = (nominaHoja.getRange("M3").getValue())*1;
  var rebajesTotalViejo = nominaHoja.getRange("O3").getValue()*1;
  var rebajesTotalNuevo = (rebajesTotalViejo+rebajesSemana)*1;
  // Logger.log(rebajesTotalNuevo);
  nominaHoja.getRange(`O3`).setValue(rebajesTotalNuevo);
}

//////////////////////////////

function generarIdentificador(area,categoria,subcatego,nombre,consecutivo){
// function generarIdentificador(){
  //  const ID_DIRECTORIO = `1NZBsJOLjnP6aojinaPUaLMnliDHYnNqVJKMYq8VhTJE`; // V0.2
  //  const ID_MASTER_GASTOS = `178M33EaTbv6rT6CA2XkA_csJlMoBI9Ej3s1T_7hq0no`;
  //  const SSID_A2 = SpreadsheetApp.getActiveSpreadsheet().getId(); // Archivo A2
  //  const subcatego = `NOMINA`;
  //  const nombre = `SARAI BELLO ALBARRAN`;
  //  const area = `PROYECTOS`;
  //  const categoria = `NOMINAS`;
  // try{
   var archivo;
   (categoria == `NOMINAS`)?archivo = `A2`:0; // SWITCH para obtener que archivo es con la categoria
   const directorioSheet = SpreadsheetApp.openById(ID_DIRECTORIO).getSheetByName(`RESTRUCTURACION`);
   const masterGastosSheet = SpreadsheetApp.openById(ID_MASTER_GASTOS).getSheetByName(`DIR-CAT-SUBCAT`);
   const directorioArreglo = directorioSheet.getRange(`B1:C`).getValues()
    .filter(fila => fila[1] != `` && fila[1] != null).map(fila => [fila[1], fila[0]]);
   const masterGArreglo = masterGastosSheet.getRange(`D1:G`).getValues()
    .filter(fila => fila[1] != `` && fila[1] != null).map(fila => [fila[0], fila[3]]);
   const areasArreglo = masterGastosSheet.getRange(`P1:Q`).getValues()
    .filter(fila => fila[1] != `` && fila[1] != null);
  const directorioObjeto = arrayToObject(directorioArreglo);
  const masterGObjeto = arrayToObject(masterGArreglo);
  const areasObjeto = arrayToObject(areasArreglo);
  var numEmpleado = cerosAntes(directorioObjeto[nombre]);
  (subcatego==`BONO GRATIFICACION`)?subcatego=`BONO GRATIFICACION`:0;
  var numSubcatego = masterGObjeto[subcatego];
  var numArea = areasObjeto[area];

  //  Logger.log(`Nombre: ${nombre}`);

   consecutivo = consecutivo.filter(fila => typeof fila[0] === `string` && fila[0].startsWith(`${numArea}-${archivo}-${numEmpleado}-${numSubcatego}`)).flat().length+1;
    const folio = seisCerosAntes(consecutivo);
  return validarDatos(archivo, numArea, numEmpleado, numSubcatego, folio);
}

//////////////////////////////

function arrayToObject(data) {
  return data.reduce((acc, row) => {
    const clave = row[0];
    const valor = row[1];
    acc[clave] = valor;
    return acc;
  }, {});
}

//////////////////////////////

function personaObject(data) {
  return data.reduce((acc, row) => {
    const nombre = row[10];
    acc[nombre] = {
      QUIEN_SOL: row[2],
      DPTO_SOL: row[3],
      USO: row[4],
      AREA_APLICA: row[6],
      CATEGORIA: row[7],
      DETALLE: row[9],
      DESTINO: row[20],
      CUENTA_CLABE: row[21],
      TITULAR: row[22]
    };
    return acc;
  }, {});
}

//////////////////////////////

function cerosAntes(numero){
  try{
    numeroStr = JSON.stringify(numero);
    switch (numeroStr.length){
    case 0: numeroStr = `0000`+numeroStr; break;
    case 1: numeroStr = `000`+numeroStr; break;
    case 2: numeroStr = `00`+numeroStr; break;
    case 3: numeroStr = `0`+numeroStr; break;
    case 4: numeroStr = numeroStr; break;
    default: numeroStr = `0000`;
    }
  }catch(err){
    return undefined;
  }
  return numeroStr;
}

//////////////////////////////

function seisCerosAntes(numero){
    numeroStr = JSON.stringify(numero);
    switch (numeroStr.length){
    case 0: numeroStr = `000000`+numeroStr; break;
    case 1: numeroStr = `00000`+numeroStr; break;
    case 2: numeroStr = `0000`+numeroStr; break;
    case 3: numeroStr = `000`+numeroStr; break;
    case 4: numeroStr = `00`+numeroStr; break;
    case 5: numeroStr = `0`+numeroStr; break;
    case 6: numeroStr = numeroStr; break;
    default: numeroStr = `000000`;
  }
  return numeroStr;
}


function validarDatos(archivo, numArea, numEmpleado, numSubcatego, folio) {
  const variables = {
    archivo,
    numArea,
    numEmpleado,
    numSubcatego,
    folio
  };
  const faltantes = Object.entries(variables)
    .filter(([_, valor]) => valor === undefined)
    .map(([nombre]) => nombre);
  if (faltantes.length > 0) {
    return `Identificador inválido. Falta: ${faltantes.join(', ')}`;
  }
  return`${numArea}-${archivo}-${numEmpleado}-${numSubcatego}-${folio}`;
}


function horasXtra(data) {
  const ss = SpreadsheetApp.openById(SSID);
  const SHEET = ss.getSheetByName(TABLAS_SHEET);
  const PLANTILLA = ss.getSheetByName(PLANTILLA_NAME);
  const lastRow = SHEET.getRange(`M12`).getDataRegion().getLastRow()+1;
  var arrNom = SHEET.getRange(`K1002:M1100`).getValues().filter(fila => fila[0] != "" && fila[0] != null)
    .map(fila => [fila[0], fila[2]]);
  var objNom = arrToObject(arrNom);
  var puNom = objNom[data.name];    //  Precio Unitario Nominas (Obtener dependiendo del data.name)
    // data.name
    // data.horas
  var formula = 
  `=IF(O${lastRow}*1>8,
  IF(O${lastRow}*1>16,(N${lastRow}*8)+(N${lastRow}*16)+(N${lastRow}*(O${lastRow}-16)*3),(N${lastRow}*8)+(N${lastRow}*(O${lastRow}-8)*2)),
N${lastRow}*O${lastRow})`;

  var arrXtra = [[
    data.name,
    (puNom/8),   //  (Precio Unitario Nomina Semanal)/8 (?)
    data.horas,
    formula
  ]];

  PLANTILLA.getRange(8,13,1,4).copyFormatToRange(SHEET,13,16,lastRow,lastRow);
  // SHEET.getRange(lastRow,13,1,1).setValues(PLANTILLA.getRange(8,13,1,1).getValues());
  SHEET.getRange(lastRow,13,1,4).setValues(arrXtra);
}

//////////////////////////////

function arrToObject(data) {
  let obj = {};
  data.forEach(fila => {
    obj[fila[0]] = fila[1];
  });
  return obj;
}

function comsiones(data) {
  const ss = SpreadsheetApp.openById(SSID);
  const SHEET = ss.getSheetByName(TABLAS_SHEET);
  const PLANTILLA = ss.getSheetByName(PLANTILLA_NAME);
  const lastRow = SHEET.getRange(`G12`).getDataRegion().getLastRow()+1;
  var arrNom = SHEET.getRange(`K1002:M1100`).getValues().filter(fila => fila[0] != "" && fila[0] != null)
    .map(fila => [fila[0], fila[2]]);
  var objNom = arrToObject(arrNom);
  var puNom = objNom[data.name];    //  Precio Unitario Nominas (Obtener dependiendo del data.name)
    // data.name
    // data.horas
  var formula = 
  `=IF(O${lastRow}*1>8,
  IF(O${lastRow}*1>16,(N${lastRow}*8)+(N${lastRow}*16)+(N${lastRow}*(O${lastRow}-16)*3),(N${lastRow}*8)+(N${lastRow}*(O${lastRow}-8)*2)),
N${lastRow}*O${lastRow})`;

  var arrXtra = [[
    data.name,
    (puNom/8),   //  (Precio Unitario Nomina Semanal)/8 (?)
    data.horas,
    formula
  ]];

  PLANTILLA.getRange(8,13,1,4).copyFormatToRange(SHEET,13,16,lastRow,lastRow);
  // SHEET.getRange(lastRow,13,1,1).setValues(PLANTILLA.getRange(8,13,1,1).getValues());
  SHEET.getRange(lastRow,13,1,4).setValues(arrXtra);
}

//////////////////////////////

function arrToObject(data) {
  let obj = {};
  data.forEach(fila => {
    obj[fila[0]] = fila[1];
  });
  return obj;
}
