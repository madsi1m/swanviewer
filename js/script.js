/**
 * ownCloud - swanviewer
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Gonzalez Labrador (CERN) <hugo.gonzalez.labrador@cern.ch>
 * @copyright Hugo Gonzalez Labrador (CERN) 2017
 */

(function ($, OC, OCA) {	
	OCA.SwanViewer = {
		swanUrl: ""
	};

	var isNotebookOpen = false;

	var loadConfig = function() {
		var url = OC.generateUrl('/apps/swanviewer/config');
		$.get(url).success(function (response) {
			if(response.swanurl) {
				OCA.SwanViewer.swanUrl = response.swanurl;
			}
		}); 
	}
	

	var isPublicPage = function () {

		if ($("input#isPublic") && $("input#isPublic").val() === "1") {
			return true;
		} else {
			return false;
		}
	};

	var getSharingToken = function () {
		if ($("input#sharingToken") && $("input#sharingToken").val()) {
			return $("input#sharingToken").val();
		} else {
			return null;
		}
	};

	var onOpen =  function (filename, data) {
		url = OC.generateUrl('/apps/swanviewer/eosinfo');
		filename = data.dir + "/" + filename;
		$.get(url, {filename: filename}).success(function (response) {
			if(response.eosinfo) {
				var info = response.eosinfo;
				var eosPath = info['eos.file'];
				var query = '?projurl=' + eosPath;
				window.open(OCA.SwanViewer.swanUrl + query, '_blank');
			} else {
				alert("could not get info from eos");
			}
		});
	};

	var onView = function(filename, data) {
		if(isPublicPage()) {
			return onViewPublic(filename, data, getSharingToken());
		}
		url = OC.generateUrl('/apps/swanviewer/load');
		filename = data.dir + "/" + filename;
		$.get(url, {filename: filename}).success(function (response) {
			if(response.data) {
				setUpEditor();
				$('#nbviewer-loader').remove();
				var iFrame = $('#nbviewer-frame');
				var doc = iFrame[0].contentDocument || iFrame[0].contentWindow.document;
				doc.write(response.data.content);
				doc.close();
			} else {
				alert(response.error);
			}
		});
	};

	var onViewPublic = function(filename, data, token) {
		console.log(filename, data, token);
		url = OC.generateUrl('/apps/swanviewer/publicload');
		$.get(url, {filename: filename, token: token}).success(function (response) {
			if(response.data) {
				setUpEditor();
				$('#nbviewer-loader').remove();
				var iFrame = $('#nbviewer-frame');
				var doc = iFrame[0].contentDocument || iFrame[0].contentWindow.document;
				doc.write(response.data.content);
				doc.close();
			} else {
				alert(response.error);
			}
		});
	};

	var onViewPublicSingleFile = function(token) {
		url = OC.generateUrl('/apps/swanviewer/publicload');
		$.get(url, {token: token}).success(function (response) {
			if(response.data) {
				setUpEditor();
				$('#nbviewer-loader').remove();
				var iFrame = $('#nbviewer-frame');
				var doc = iFrame[0].contentDocument || iFrame[0].contentWindow.document;
				doc.write(response.data.content);
				doc.close();
			} else {
				alert(response.error);
			}
		});
	};

	function closeFile(callback) {
		if(isNotebookOpen) {
			$('#nbviewer').remove();
			$('#app-navigation').show();
			$('#app-content').show();
			isNotebookOpen = false;

			if(callback) {
				callback();
			}
		}
	}

	function setUpEditor(closeCallBack, publicLinkRender) {
		isNotebookOpen =  true;
		var mainDiv = $('#nbviewer');

		if(mainDiv.length < 1)
		{
			mainDiv = $('<div id="nbviewer"></div>');
			mainDiv.css('position', 'absolute');
			mainDiv.css('top', '0');
			mainDiv.css('left', '0');
			mainDiv.css('width', '100%');
			mainDiv.css('height', '100%');
			mainDiv.css('z-index', '200');
			mainDiv.css('background-color', '#fff');

			var frame = $('<iframe id="nbviewer-frame"></iframe>');
			frame.css('position', 'absolute');
			frame.css('top', '0');
			frame.css('left', '0');
			frame.css('width', '100%');
			frame.css('height', '100%');

			mainDiv.append(frame);
			if(publicLinkRender) {
				$('#preview').append(mainDiv);
			} else {
				$('#content').append(mainDiv);
			}

			//$(document.body).append(mainDiv);
		}

		var loadingImg = $('<div id="nbviewer-loader"></div>');
		loadingImg.css('position', 'absolute');
		loadingImg.css('top', '50%');
		loadingImg.css('left', '50%');
		loadingImg.css('width', 'auto');
		loadingImg.css('height', 'auto');
		var img = OC.imagePath('core', 'loading-dark.gif');
		var imgContent = $('<img></img>');
		imgContent.attr('src',img);
		loadingImg.append(imgContent);

		var closeButton = $('<div></div>');
		closeButton.css('position', 'absolute');
		closeButton.css('top', '0');
		closeButton.css('left', '95%');
		closeButton.css('width', 'auto');
		closeButton.css('height', 'auto');
		closeButton.css('z-index', '200');
		closeButton.css('background-color', '#f00');
		var closeImg = OC.imagePath('core', 'actions/close.svg');
		var closeImgContent = $('<img></img>');
		closeImgContent.attr('src', closeImg);
		closeButton.append(closeImgContent);

		closeButton.click(function() { closeFile(closeCallBack); });

		$('#app-navigation').hide();
		$('#app-content').hide();

		mainDiv.append(loadingImg);
		mainDiv.append(closeButton);
	}


	$(document).ready(function () {
		loadConfig();
		if (OCA && OCA.Files) {
			OCA.Files.fileActions.register('application/pynb', 'Open in SWAN', OC.PERMISSION_UPDATE, OC.imagePath('core', 'actions/play'), onOpen);
			OCA.Files.fileActions.register('application/pynb', 'Default View', OC.PERMISSION_READ, OC.imagePath('core', 'actions/play'), onView);

			OCA.Files.fileActions.setDefault('application/pynb', 'Default View');
		}
		// Doesn't work with IE below 9
		if(!$.browser.msie || ($.browser.msie && $.browser.version >= 9)){
			if ($('#isPublic').val() && $('#mimetype').val() === 'application/pynb' && $("input#passwordProtected").val() === "false") {
				var sharingToken = $('#sharingToken').val();
				onViewPublicSingleFile(sharingToken);
				// add Open in SWAN button
				var url = $("#downloadURL").val();
				url = 'https://cern.ch/swanserver/cgi-bin/go?projurl=' + url;
				var button = '<a href="' + url + '" target="_blank"><img class="svg" alt="" src="/apps/swanviewer/img//badge_swan_white_150.svg"></a>';
				$('.directLink').parent().append($(button));
			}
		}
	});

})(jQuery, OC, OCA);
