var csv = "";

var dbCache = {
    filename: null,
    SQL: null,
    db: null
};

// Connect to the database, reusing the already-open connection when the
// same file is queried again instead of re-fetching and re-parsing it.
async function dbConnect(filename) {
    if (dbCache.filename === filename && dbCache.db) {
        return dbCache.db;
    }

    const sqlPromise = dbCache.SQL ? Promise.resolve(dbCache.SQL) : initSqlJs();
    const dbPromise = fetch(filename).then(response => response.arrayBuffer());
    const [SQL, database] = await Promise.all([sqlPromise, dbPromise]);

    if (dbCache.db) {
        dbCache.db.close();
    }

    const db = new SQL.Database(new Uint8Array(database));

    dbCache = { filename: filename, SQL: SQL, db: db };

    return db;
}

async function dbQuery(dbFile, query, params) {
    var db = await dbConnect(dbFile);
    return db.exec(query, params || {});
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function zeroPad(num) {
    return num.toString().padStart(7, "0");
}

async function updateTableWithQuery(results) {
    var html = "";

    var timerStart = new Date().getTime();

    results.forEach(result => {
        html += "<tr>";
        var columnId = 0;
        result.forEach(value => {
            columnId++;

            if (columnId == 1) {
                var paddedNum = zeroPad(value);
                html += "<td><a href='https://www.imdb.com/title/tt" + paddedNum + "' target='_blank'>" + paddedNum + "</a></td>";
                return;
            }

            html += "<td>" + escapeHtml(value) + "</td>";
        });
        html += "</tr>";
    });

    // Set a small timeout to allow the user to see the query info before the table is updated
    setTimeout(function() {
        $("#resultsTable tbody").html(html);
        $("#resultsTable").trigger("update");

        var timerDone = (new Date().getTime() - timerStart) / 1000;

        $("#queryInfo").append("done in " + timerDone + " seconds");
    }, 500);
}

// Builds a parameterized query (rather than splicing user input into SQL text)
// so a stray quote or SQL metacharacter in a field can't change the query.
function createQuery() {
    var dateFrom = $("#dateFrom").val();
    var dateTo = $("#dateTo").val();
    var region = $("#region").val();
    var festival = $("#festival").val();
    var attributes = $("#attributes").val();

    var conditions = [];
    var params = {};

    if (dateFrom != "" && dateFrom != "%" && dateTo != "" && dateTo != "%") {

        // If % is used, throw error
        if (dateFrom.includes("%") || dateTo.includes("%")) {
            $("#queryErrors").css("display", "block");
            $("#queryErrors").html("Date cannot contain %");
            return null;
        }

        if ($("#date_invert").is(":checked")) {
            conditions.push("date NOT BETWEEN :dateFrom AND :dateTo");
        } else {
            conditions.push("date BETWEEN :dateFrom AND :dateTo");
        }
        params[":dateFrom"] = dateFrom;
        params[":dateTo"] = dateTo;
    }

    if (region != "" && region != "%") {
        conditions.push("region LIKE :region");
        params[":region"] = region;
    }
    if (festival != "" && festival != "%") {
        conditions.push("festival LIKE :festival");
        params[":festival"] = festival;
    }
    if (attributes != "" && attributes != "%") {
        conditions.push("attributes LIKE :attributes");
        params[":attributes"] = attributes;
    }

    if (conditions.length == 0) {
        $("#queryErrors").css("display", "block");
        $("#queryErrors").html("No search parameters entered.");
        return null;
    }

    return {
        sql: "SELECT * FROM items WHERE " + conditions.join(" AND "),
        params: params
    };
}

function exportCSV() {
    var hiddenElement = document.createElement("a");
    hiddenElement.href = "data:text/csv;charset=utf-8," + encodeURI("﻿" + csv);
    hiddenElement.target = "_blank";
    hiddenElement.download = "imdb_search_export.csv";
    hiddenElement.click();
}

function csvField(value) {
    var field = String(value == null ? "" : value);
    if (/[",\n]/.test(field)) {
        field = '"' + field.replace(/"/g, '""') + '"';
    }
    return field;
}

function createCSV(result) {
    var header = ["ID", "Ordering", "Date", "Region", "Premiere", "Wide", "Premiere Type", "Festival", "Attributes"];
    var lines = [header.join(",")];

    result[0].values.forEach(row => {
        lines.push(row.map(csvField).join(","));
    });

    return lines.join("\n");
}

async function queryDatabase(sql, params) {
    var selectedDatabase = $("#database").val();

    return await dbQuery("databases/" + selectedDatabase, sql, params);
}

async function getDatabases() {
    // Get all sqlite files in folder
    $.ajax({
        url: "./getDatabases.php",
        success: function(result) {
            var databases = JSON.parse(result);
            var html = "";

            var c = 0;
            databases.forEach(database => {
                if (c == 0) {
                    html += "<option value='" + database + "' selected>" + database + "</option>";
                    c++;
                    return;
                }

                html += "<option value='" + database + "'>" + database + "</option>";
            });

            $("#database").html(html);
        }
    });
}

$(function() {
    getDatabases();

    $("#resultsTable").tablesorter({});

    // Make the form querable by the user without reloading the page
    $("#myForm").submit(function(e) {
        e.preventDefault();

        $("#queryErrors").css("display", "none");
        $("#resultsTable tbody").html("");
        $("#exportCSV").prop("disabled", true);
        $("#submit").prop("disabled", true);

        var query = createQuery();

        if (query === null) {
            $("#submit").prop("disabled", false);
            return;
        }

        // This may take some time, so give the user some feedback
        $("#queryInfo").css("display", "block");
        $("#queryInfo").html("Querying database...");

        var waitForQuery = setInterval(function() {
            $("#queryInfo").append(".");
        }, 1000);

        var timerStart = new Date().getTime();

        // Run the query, and wait for the results
        queryDatabase(query.sql, query.params).then(result => {
            clearInterval(waitForQuery);
            $("#submit").prop("disabled", false);

            var timerDone = (new Date().getTime() - timerStart) / 1000;

            // If the query failed, show the error message
            if (result.length == 0) {
                $("#queryErrors").css("display", "block");
                $("#queryErrors").html("No results found.");
                $("#queryInfo").css("display", "none");
                return;
            }

            // If the query succeeded, show the number of results
            $("#queryInfo").append("...done in " + timerDone + " seconds. Found " + result[0].values.length + " results<br />Rendering table...");
            $("#exportCSV").prop("disabled", false);

            updateTableWithQuery(result[0].values);
            csv = createCSV(result);
        }).catch(error => {
            clearInterval(waitForQuery);
            $("#submit").prop("disabled", false);
            $("#queryInfo").css("display", "none");
            $("#queryErrors").css("display", "block");
            $("#queryErrors").html("Query failed: " + escapeHtml(error.message || error));
        });
    });
});
