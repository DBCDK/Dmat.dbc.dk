/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

var mw = [];
var cnt = 0;
var wtop = 0;
var left = 0;
var topadd = 700;
var leftadd = 610;
var modType = '';
var modKey = '';
var modIaw = false;
// var modClone = '';

function setDBxColor() {
    var dbf = document.getElementById('DBF').checked;
    var dbr = document.getElementById('DBR').checked;
    if (dbf) {
        document.getElementById('DBRAMME').style.backgroundColor = '#e0ffff';
        return true;
    }
    if (dbr) {
        document.getElementById('DBRAMME').style.backgroundColor = '#f0e68c';
        return true;
    }
    document.getElementById('DBRAMME').style.backgroundColor = 'inherit';
    // alert('xx');
    // alert("dbf:" + dbf + ", dbr:" + dbr);
}

function faustOK(event) {
    val = event.originalTarget.firstChild.data;
    event.dataTransfer.setData("faust", val);
}

function faustDrop(event) {
    event.preventDefault();
    var data = event.dataTransfer.getData("faust");
    document.getElementById('faust').defaultValue = data;
}

function allowDrop(event) {
    event.preventDefault();
}

window.onbeforeprint = function () {

    // window.submit();
    $("#topbar").hide();
    $(".reveal-modal").hide();
    // alert('This will be called before the user prints.');
};
window.onafterprint = function () {
    // location.reload();
    // alert('Siden er printet');
    $("#topbar").show();
    $(".reveal-modal").show();
}


function callhref(url) {
    var isbn = document.getElementById('expisbn').value;
    var saxodate = document.getElementById('published').value;
    var bkxwc = document.getElementById('BKXWC').value;
    url = url + '&expisbn=' + isbn + '&published=' + saxodate + '&bkxwc=' + bkxwc;
    var myWindow = window.open(url, '_self');

}

function emptywin(dta) {
    var xx = dta;
    myWindow = window.open("", "_blank", "toolbar=no, scrollbars=yes, \n\
                    resizable=yes, top=0, left=0, width=1800, height=800");
    myWindow.document.write(dta);
}
function callnewpg(url, seqno) {
    if (seqno) {
        if (seqno > 0) {
            myWindow = window.open(url, "_blank", "toolbar=no, scrollbars=yes, \n\
                    resizable=yes, top=0, left=0, width=1800, height=800");
        }
    }
}

// $(function () {
//     $('.datecell').fdatepicker({
//         format: 'dd-mm-yyyy', language: 'da'
//     })
// });

function newWeekCode(type, key) {
    modType = type;
    modKey = key;
    var id = type + key;
    var allClass = $('#' + id).attr('class');
    modIaw = document.getElementById(id).getAttribute('iaw');
    modClass = '';
    arrClass = allClass.split(' ');
    for (i = 0; i < arrClass.length; i++) {
        classelement = arrClass[i];
        if (classelement.slice(0, 7) == 'userbox') {
            // modClass = classelement;
            // modId = 'colorbox' + classelement.slice(7, 10);
            document.getElementById(id).checked = true;
        }
    }
    var wc = $('#' + id).attr('value');
    // modClone = $('#newWeekCode').clone(true);
    $('#datebox').attr('value', wc);
    $('#newWeekCode').foundation('reveal', 'open');


}

function newWeekCode2(par) {
    var color = 'nonecolor';
    var weekcode = $('#datebox').val();
    var allRadio = $("input:radio");
    for (i = 0; i < allRadio.length; i++) {
        el = allRadio[i];
        if (el.checked) {
            color = el.defaultValue;
        }
    }
    if (par == 'out') {
        weekcode = 'Udgår';
    }
    if (par == 'blank') {
        weekcode = '';
    }
    var order = 'newWeekCode';
    $('#newWeekCode').foundation('reveal', 'close');
    url = 'ProductionPlan.php?order=' + order + '&color=' + color +
        '&weekcode=' + weekcode + '&type=' + modType + '&key=' + modKey;
    // alert(url);
    $.post('ProductionPlan.php',
        {
            order: order,
            type: modType,
            key: modKey,
            color: color,
            weekcode: weekcode,
            iaw: modIaw
        },
        function (data, status) {
            // alert('data:' + data);
            lines = data.split('+');
            lnOne = lines[0].split('|');
            idOne = lnOne[0];
            dataOne = lnOne[1];
            $(idOne).html(dataOne);
            $('.datecell').fdatepicker({
                format: 'dd-mm-yyyy', language: 'da'
            })

        }
    )
    ;
}
function canceledUncanceled(type, key) {
    elementid = type + key;
    var geteven = document.getElementById(elementid).getAttribute('geteven');
    var newdate = document.getElementById(elementid).getAttribute('value');

    url = 'ProductionPlan.php?order=canUncan&type=' + type +
        '&key=' + key + '&newdate=' + newdate + '&geteven=' + geteven;
    // alert(url);
    $.post('ProductionPlan.php',
        {
            order: 'canUncan',
            type: type,
            key: key,
            newdate: newdate,
            geteven: geteven
        },
        function (data, status) {
            // alert('data:' + data);
            lines = data.split('+');
            lnOne = lines[0].split('|');
            idOne = lnOne[0];
            dataOne = lnOne[1];
            // location.reload();
            // alert(idOne);
            // alert(dataOne);

            // lnTwo = lines[1].split('|');
            // idTwo = lnTwo[0];
            // dataTwo = lnTwo[1];
            $(idOne).html(dataOne);
            // $(idTwo).html(dataTwo);
            $('.datecell').fdatepicker({
                format: 'dd-mm-yyyy', language: 'da'
            })

        }
    )
    ;

}

function delLU(seqno) {
    // var url = "delLU.php?cmd=delete&seqno=" + seqno;
    // alert(url);
    $.post('delLU.php',
        {
            cmd: 'delete',
            seqno: seqno
        },
        function (data, status) {
            // alert("Data:" + data + "\n\nStatus:" + status);
            if (status != 'success') {
                alert("Return status from delLU.php is not success:" + status);
            }
            if (data != 'OK') {
                alert("Kunne ikke slette lektørudtalelsen \n\n" + data);
            }
        }
    )
    $('#delluok').foundation('reveal', 'close');
}

function updateDate(type, key) {
    elementid = type + key;

    var newdate = document.getElementById(elementid).value;
    var geteven = document.getElementById(elementid).getAttribute('geteven');
    var iaw = document.getElementById(elementid).getAttribute('iaw');

    var url = 'ProductionPlan.php?order=getNewWeek&type=' + type +
        '&key=' + key + '&newdate=' + newdate + '&geteven=' + geteven + '&iaw=' + iaw;
    // alert(url);
    $.post('ProductionPlan.php',
        {
            order: 'getNewWeek',
            type: type,
            key: key,
            newdate: newdate,
            geteven: geteven,
            iaw: iaw
        },
        function (data, status) {
            lines = data.split('+');
            lnOne = lines[0].split('|');
            idOne = lnOne[0];
            dataOne = lnOne[1];

            lnTwo = lines[1].split('|');
            idTwo = lnTwo[0];
            dataTwo = lnTwo[1];
            $(idOne).html(dataOne);
            $(idTwo).html(dataTwo);
            $('.datecell').fdatepicker({
                format: 'dd-mm-yyyy', language: 'da'
            })
        }
    );

}
function openCloseWin(cmd, link, oldstatus) {
    var myWindow;

    if (cmd === 'none') {
        len = mw.length;
        var i;
        //window.alert('Len:' + len);
        for (i in mw) {
            mw[i].close();
        }
        cnt = 0;
        wtop = 0;
        left = 0;
        var href = '?cmd=none&seqno=' + link;
        myWindow = window.open(href, '_self');
    }
    //http://devel7.dbc.dk/~hhl/posthus/eVALU/eVa.php?cmd=choose&seqno=31623&base=Basis&lokalid=0%20920%20576%204&bibliotek=870970&type=title&matchtype=204&oldstatus=eVa
    if (cmd == 'insertLink') {
        var href = '?cmd=insertLink&' + link + '&oldstatus=' + oldstatus;
        myWindow = window.open(href, '_self');
    }

    if (cmd === 'choosen') {
        len = mw.length;
        var i;
//        window.alert('oldstatus:' + oldstatus);
        for (i in mw) {
            mw[i].close();
        }
        cnt = 0;
        wtop = 0;
        left = 0;
        var href = '?cmd=choose&' + link + '&oldstatus=' + oldstatus;
        myWindow = window.open(href, '_self');
    }
    if (cmd === 'open') {
        var href = 'fetchFromLibV3.php?' + link;
        myWindow = window.open(href, "_blank", "toolbar=no, scrollbars=yes, \n\
                    resizable=yes, top=" + wtop + ", left=" + left + ", width=600, height=800");
        mw[cnt++] = myWindow;
        //len = mw.length;
        left += leftadd;
        if (cnt == 3 || cnt == 6 || cnt == 9) {
            wtop += topadd;
            topadd += topadd;
            left = 0;
        }

        //window.alert('len:' + len + ' cnt:' + cnt );
    }
    if (cmd === 'xml') {
        var href = 'fetchXml.php?seqno=' + link;
        myWindow = window.open(href, "_blank", "toolbar=no, scrollbars=yes, \n\
                    resizable=yes, top=" + wtop + ", left=" + left + ", width=600, height=800");
        //myWindow.document.write(link);
        mw[cnt++] = myWindow;
        left += leftadd;
        if (cnt == 3 || cnt == 6 || cnt == 9) {
            wtop += topadd;
            topadd += topadd;
            left = 0;
        }
    }

    if (cmd === 'info') {
//        var href = 'tekster/help.html';
        var href = 'http://wiki.dbc.dk/bin/view/Data/EvaElu';
        myWindow = window.open(href, "_blank", "toolbar=no, scrollbars=yes, \n\
                    resizable=yes, top=0, left=0, width=800, height=600");
        //myWindow.document.write(link);
        //mw[cnt++] = myWindow;
        cnt++;
        left += leftadd;
        if (cnt == 3 || cnt == 6 || cnt == 9) {
            wtop += topadd;
            topadd += topadd;
            left = 0;
        }
    }

    if (cmd === 'close') {
        len = mw.length;
        var i;
        //window.alert('Len:' + len);
        for (i in mw) {
            mw[i].close();
        }
        cnt = 0;
        wtop = 0;
        left = 0;
        // event.preventDefault();
        // return false;
    }
}

function haveChoosen(cmd) {
    var choosen = true;
    if (!document.getElementById("BKM").checked) {
        if (!document.getElementById("BKMV").checked) {
            if (!document.getElementById("DBF").checked && !document.getElementById("DBR").checked) {
                if (!document.getElementById("V").checked) {
                    if (!document.getElementById("B").checked) {
                        if (!document.getElementById("S").checked) {
                            choosen = false;
                        }
                    }
                }
            }
        }
    }
    if (choosen) {
        openCloseWin(cmd, 'xx');
        return true;
    } else {
        return false;
    }
}


function tst(cmd, link, oldstatus) {
    var myWindow;

    if (cmd === 'close') {
        len = mw.length;
        var i;
        //window.alert('Len:' + len);
        for (i in mw) {
            mw[i].close();
        }
        cnt = 0;
        wtop = 0;
        left = 0;
        // event.preventDefault();
        return true;
    }
}


function InputWC(arg, ddwc, tempfaust) {
    var disabL = document.createAttribute('disabled');
    var x = document.getElementById('PrintedTemplate');
    document.getElementById('LekOk').attributes.setNamedItem(disabL);
    document.getElementById('wcerror').innerHTML = '';
    document.getElementById("L").checked = true;
    if (tempfaust) {
        // Skal ikke bruges, måske genoplives derfor ikke fjernet.
        // x.style.display = 'block';
        x.style.display = 'none';
    }
    var char = '';
    var txt = '';
    var data = document.getElementById(arg).value;
    for (var i = 0; i < data.length; i++) {
        if (data[i] !== ' ') {
            var y = parseInt(data[i]);
            y = y.toString();
            if (y !== 'NaN') {
                char += y;
            }
        }
    }
    if (char.length == 0) {
        document.getElementById('LekOk').removeAttribute('disabled');
    }
    if (char.length == 6) {
        if (char > ddwc) {
            document.getElementById('LekOk').removeAttribute('disabled');
        } else {
            txt = 'Ugekoden skal være nyere end ' + ddwc;
            document.getElementById('wcerror').innerHTML = txt;
        }
    }
    document.getElementById(arg).value = char;
}


function SetEluBKM(arg, tempfaust) {
//    var L = document.getElementsByClassName('L');
//    var sta = document.getElementById("L").checked;
//     window.alert('nu er vi her ' + arg + 'L:' + L + 'Status:' + tempfaust);
    var x = document.getElementById('PrintedTemplate');
    if (arg == 'L') {
        document.getElementById("BKMV").checked = false;
        if (!document.getElementById("L").checked) {
            document.getElementById("BKMV").checked = false;
            x.style.display = 'none';
        } else {
            if (tempfaust) {
                // Skal ikke bruges, måske genoplives derfor ikke fjernet.
                // x.style.display = 'block';
                x.style.display = 'none';
            }
        }
    }

    // if (arg == 'BKX') {
    //     document.getElementById("BKMV").checked = false;
    //     if (document.getElementById("BKMV").checked) {
    //         document.getElementById("L").checked = true;
    //         x.style.display = 'block';
    //     }
    // }

    if (arg == 'BKMV') {
        // document.getElementById("BKMV").checked = false;
        document.getElementById("L").checked = false;
        if (tempfaust) {
            // Skal ikke bruges, måske genoplives derfor ikke fjernet.
            // x.style.display = 'block';
            x.style.display = 'none';
        }
    }

//    var BKMV = document.getElementsByClassName('BKMV');
//    var L = document.getElementsByClassName('L');
//        window.alert('nu er vi her ' + arg + 'L:' + L)

//    var BKX = document.getElementsByClassName('BKX');
//     if (document.getElementById("BKMV").checked) {
//        if (document.getElementById("BKX").checked) {
//            if (arg == 'BKMV') {
//                txt = " <br /><br /><strong>BKMV</strong> vælges!!!";
//                txt = "\n\nBKMV vælges\n";
//            } else {
//                txt = " <br /><br />Slet <strong>BKMV</strong> først!!!";
//            }
//            window.alert('BKX og BKMV kan ikke sættes samtidig! ' + txt)
//        }
//    }
}

function searchGoogle(arg) {
    if (arg == '') {
        var arg = document.getElementById('expisbn').value;
    }
    var href = 'https://www.google.dk/search?q=' + arg;
    myWindow = window.open(href, '_blank');
}

function searchSaxo(arg) {
    if (arg == '') {
        var arg = document.getElementById('expisbn').value;
    }
    var href = 'https://www.saxo.com/dk/soeg/bog?query=' + arg;
    myWindow = window.open(href, '_blank');
}

function SetBKM(arg) {

    var VBS = document.getElementsByClassName('VBS');
//    var BKMV = document.getElementsByClassName('BKMV');
//    var BKM = document.getElementsByClassName('BKM');
//    var BKX = document.getElementsByClassName('BKX');
    var chk = false;
//    var bkmvchk = false;
//    var bkxchk = false;
//    var bkmchk = false;
    var i;
//    var id;

//if (document.getElementById("BKM").checked  ) {
//    window.alert('BKM');
//}

    if (arg == 'DBR') {
        document.getElementById("DBF").checked = false;
    }
    if (arg == 'DBF') {
        document.getElementById("DBR").checked = false;
    }
    for (i = 0; i < VBS.length; i++) {
        if (VBS[i].checked) {
            chk = true;
        }
    }

    if (chk) {
        document.getElementById("BKM").checked = true;
        if (document.getElementById("DBR").checked != true) {
            document.getElementById("DBF").checked = true;
        }
    } else {
        document.getElementById("BKM").checked = false;
    }

    if (document.getElementById("BKMV").checked) {
        if (document.getElementById("BKM").checked) {
            if (arg == 'BKMV') {
                // txt = " <br /><br /><strong>BKMV</strong> vælges!!!";
                txt = "\n\nBKMV vælges\n";
            } else {
                txt = " <br /><br />Slet <strong>BKMV</strong> først!!!";
                txt = "\n\nSlet BKMV først!!";
            }

//            document.getElementById("BKMV").checked = false;
//            Modal.open({
//                content: "<div style='margin: 50px;'><strong>BKM</strong> og <strong>BKMV</strong> kan ikke sættes samtidig" + txt + "</div>",
//                width: 'auto',
//                height: 'auto',
//                hideClose: false,
//                closeAfter: 5,
//                draggable: true
//            });
            window.alert('BKM og BKMV kan ikke sættes samtidig! ' + txt)
//        } else {
        }
    }
    if (document.getElementById("BKMV").checked) {
        document.getElementById("BKM").checked = false;
        document.getElementById("DBF").checked = false;
        document.getElementById("DBR").checked = false;
        document.getElementById("V").checked = false;
        document.getElementById("B").checked = false;
        document.getElementById("S").checked = false;
    }
//    }
    setDBxColor();

    if (document.getElementById("BKX")) {
        if (document.getElementById("BKX").checked) {
            if (!document.getElementById("BKM").checked) {
                document.getElementById("BKX").checked = false;
                Modal.open({
                    content: "<div style='margin: 50px;'><strong>BKX</strong> kan kun sættes hvis <strong>BKM</strong> er sat!</div>",
                    width: 'auto',
                    height: 'auto',
                    hideClose: false,
                    closeAfter: 5,
                    draggable: true
                });
//            window.alert('BKX kan kun sættes hvis BKM er sat!');
            }
        }
    }
}

function Disable_L_BKX_BKMV() {
    var disabL = document.createAttribute('disabled');
    document.getElementById("L").attributes.setNamedItem(disabL);
    var disabBKX = document.createAttribute('disabled');
    document.getElementById("BKX").attributes.setNamedItem(disabBKX);
    var disabBKMV = document.createAttribute('disabled');
    document.getElementById("BKMV").attributes.setNamedItem(disabBKMV);
    var disabISBN = document.createAttribute('disabled');
    document.getElementById("expisbn").attributes.setNamedItem(disabISBN);
}

function isbnInput() {
    var char = '';
    var txt = '';
    var x = document.getElementById("expisbn").value;
    for (var i = 0; i < x.length; i++) {
        if (x[i] !== ' ' & x[i] != '-') {
            var y = parseInt(x[i]);
            y = y.toString();
            if (y !== 'NaN') {
                char += y;
            }
        }
    }
    if (char.length > 13) {
        char = char.substr(0, 13);
    }
    document.getElementById("expisbn").value = char;
    // x = document.getElementById("expisbn").value;
    // if (x.length > 0) {
    //     var disabL = document.createAttribute('disabled');
    //     document.getElementById("L").attributes.setNamedItem(disabL);
    //     var disabBKX = document.createAttribute('disabled');
    //     document.getElementById("BKX").attributes.setNamedItem(disabBKX);
    //     var disabBKMV = document.createAttribute('disabled');
    //     document.getElementById("BKMV").attributes.setNamedItem(disabBKMV);
    // }
    // if (x.length < 1) {
    //     document.getElementById("L").attributes.removeNamedItem('disabled');
    //     document.getElementById("BKX").attributes.removeNamedItem('disabled');
    //     document.getElementById("BKMV").attributes.removeNamedItem('disabled');
    // }
}


function faustInput() {
    var char = '';
    var txt = '';
    var x = document.getElementById("faust").value;
    for (var i = 0; i < x.length; i++) {
        if (x[i] !== ' ') {
            var y = parseInt(x[i]);
            y = y.toString();
            if (y !== 'NaN') {
                char += y;
            }
        }
    }
    for (var i = 0; i < char.length; i++) {
        if (i === 1 || i === 4 || i === 7) {
            txt += ' ';
        }
        txt += char[i];
    }
    document.getElementById("faust").value = txt;
}
