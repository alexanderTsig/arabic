var finish_url;

// Results Screen vars
var g_strPlayer = "chico";
var g_arrResults = new Array();
var g_oQuizResults = new Object();
g_oQuizResults.oOptions = new Object();

// Message Delimitors
var g_strDelim = "|~|";
var g_strInteractionDelim = "|#|";

function quiz_DoJSCommand(command, args) {
	var strCommand = command;
	var strArgs = ReplaceAll(args, "|$|", "%");
	quiz_DoFSCommand(strCommand, strArgs) 
}

function ReplaceAll(strTarget, strChar, strNew) {
	var arrRemoved = strTarget.split(strChar);	
	return arrRemoved.join(strNew);
}

function quiz_DoFSCommand(command, args) {
	console.log(command + ", " + args);
	/* args = String(args);
	command = String(command);

	var arrArgs = args.split(g_strDelim);

	switch (command) {
		case "CC_SetInteractionDelim":
			g_strInteractionDelim = args;
			break;
			
		case "CC_SetDelim":
			g_strDelim = args;
 			break;
			
		case "CC_StoreQuestionResult":		
			StoreQuestionResult(parseFloat(arrArgs[0]), arrArgs[1], arrArgs[2], arrArgs[3], arrArgs[4] ,arrArgs[5], arrArgs[6], arrArgs[7], arrArgs[8], arrArgs[9]);
			break;
			
		case "CC_StoreQuizResult":
			SubmitResults(arrArgs);
			break;
			
		case "CC_ClosePlayer":
			if (typeof finish_url === "undefined")
				finish_url = "/level/" + pta.level
			window.location = finish_url;
			break;
		
		case "CC_LogInteraction":
			break;
			
		default:
			console.log(command + " handler not implemented!");
			break;
	} */
}

/* function SubmitResults(arrArgs) {
	g_oQuizResults.dtmFinished = new Date();
	g_oQuizResults.strResult = arrArgs[0];
	g_oQuizResults.strScore = arrArgs[1];
	g_oQuizResults.strPassingScore = arrArgs[2];
	g_oQuizResults.strMinScore = arrArgs[3];
	g_oQuizResults.strMaxScore = arrArgs[4];
	g_oQuizResults.strPtScore = arrArgs[5];
	g_oQuizResults.strPtMax = arrArgs[6];
	g_oQuizResults.strTitle = arrArgs[7];

	var submit_url = "//portal.pathtoarabic.com/level/" + pta.level + "/" + pta.lesson + "/results";

	$.post(submit_url, {
		"nonce":         $("#quiz").data("nonce"),
		"score":         g_oQuizResults.strScore,
		"passing_score": g_oQuizResults.strPassingScore,
		"min_score":     g_oQuizResults.strMinScore,
		"max_score":     g_oQuizResults.strMaxScore,
		"points":        g_oQuizResults.strPtScore,
		"max_points":    g_oQuizResults.strPtMax
	}, function(response) {
		// Bail out if the submission failed
		if (response.code !== 0) {
			$('#alert').attr("class", "alert alert-error").html('We are very sorry but your results could not be submitted. <a href="#">More info</a>').show();
			return;
		}
		
		if (response.pass === true) {
			if (response.level > response.oldlevel && response.level > response.startlevel) {
				// The user advanced to the next level
				window.location = "/level/" + response.level + "/congratulations";
			}
			else {
				// The user advanced to the next lesson.
				$('#alert').attr("class", "alert alert-success").html('Well done! You have successfully activated the <a href="/level/' + response.level + '/' + response.lesson + '/video">next lesson</a>.').show();
				finish_url = "/level/" + getlevel();
			}
		}
		else {
			// The user failed to pass
			$('#alert').html('Your score didn\'t quite make the grade. Perhaps you would like to <a href="#" onClick="window.location.reload()">try again?</a>').show();
		}
	});
}

function QuestionResult(nQuestionNum, strQuestion, strResult, strCorrectResponse, strStudentResponse, nPoints, strInteractionId, strObjectiveId, strType, strLatency) {
	if (nPoints < 0)
		nPoints = 0;

	if (strCorrectResponse == "")
		strCorrectResponse = "&nbsp;";

	this.nQuestionNum = nQuestionNum
	this.strQuestion = strQuestion;
	this.strCorrectResponse = strCorrectResponse;
	this.strStudentResponse = strStudentResponse;
	this.strResult = strResult;
	this.nPoints = nPoints;
	this.bFound = false;
	this.dtmFinished = new Date();
	this.strInteractionId = strInteractionId;
	this.strObjectiveId = strObjectiveId;
	this.strType = strType;
	this.strLatency = strLatency;
}

function StoreQuestionResult(nQuestionNum, strQuestion, strResult, strCorrectResponse, strStudentResponse, nPoints, strInteractionId, strObjectiveId, strType, strLatency) {

	var oQuestionResult = new QuestionResult(nQuestionNum, strQuestion, strResult, strCorrectResponse, strStudentResponse, nPoints, strInteractionId, strObjectiveId, strType, strLatency);
	var nIndex = g_arrResults.length;

	// Lets see if we have answered the question before
	for (var i = 0; i < g_arrResults.length; i++) {
		if (g_arrResults[i].nQuestionNum == oQuestionResult.nQuestionNum && strQuestion == g_arrResults[i].strQuestion) {
			nIndex = i;
			break;
		}
	}

	g_arrResults[nIndex] = oQuestionResult;
} */
