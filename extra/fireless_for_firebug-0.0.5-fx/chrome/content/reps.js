FBL.ns(function() { with (FBL) {
    // A map of editor protocols to their string names.
    // Each of these should work with the format "protocol://open?url=%s&line=%s".
    // They should also each have localized strings.
    const editorProtocols = {
        txmt: "Textmate",
        mvim: "MacVim",
        emacs: "Emacs"
    };

    var stringBundle = document.getElementById("strings");

    var eps = Components.classes["@mozilla.org/uriloader/external-protocol-service;1"]
        .getService(Ci.nsIExternalProtocolService);
    var sl = Firebug.getRep(new FBL.SourceLink());

    function cacheLessDebugInfo(sourceLink) {
        if (sourceLink.type != "css" || sourceLink.lessDebugInfo) {
            sourceLink.lessDebugInfo = {};
            return;
        }

        var rules = sourceLink.object.parentStyleSheet.cssRules;
        for(var i=0; i<rules.length-1; i++)
        {
            var styleRule = rules[i+1];
            if (styleRule.type != CSSRule.STYLE_RULE) continue;
            styleRule.lessDebugInfo = {};

            var mediaRule = rules[i];
            if (mediaRule.type != CSSRule.MEDIA_RULE) continue;

            if (mediaRule.media.mediaText != "-less-debug-info") continue;

            for (var j=0; j<mediaRule.cssRules.length; j++)
            {
                styleRule.lessDebugInfo[mediaRule.cssRules[j].selectorText] =
                    mediaRule.cssRules[j].style.getPropertyValue("font-family");
            }
        }

        sourceLink.lessDebugInfo = sourceLink.object.lessDebugInfo || {};
        return;
    }

    sl.getSourceLinkTitle = function(sourceLink)
    {
        if (!sourceLink)
            return "";

        cacheLessDebugInfo(sourceLink);

        try
        {
            var fileName = getFileName(sourceLink.lessDebugInfo["filename"] || sourceLink.href);
            fileName = decodeURIComponent(fileName);
            fileName = cropString(fileName, 17);
        }
        catch(exc)
        {
        }

        return $STRF("Line", [fileName, sourceLink.lessDebugInfo["line"] || sourceLink.line]);
    };

    sl.copyLink = function(sourceLink)
    {
        var lessFilename = sourceLink.lessDebugInfo["filename"];
        if (lessFilename)
        {
            var url = splitURLTrue(lessFilename);
            copyToClipboard(url.path + "/" + url.name);
        }
        else
            copyToClipboard(sourceLink.href);
    };

    sl.getTooltip = function(sourceLink)
    {
        return decodeURI(sourceLink.lessDebugInfo["filename"] || sourceLink.href);
    };

    var oldGetContextMenuItems = sl.getContextMenuItems;
    sl.getContextMenuItems = function(sourceLink, target, context) {
        var items = oldGetContextMenuItems(sourceLink, target, context);

        if (!sourceLink.lessDebugInfo["filename"])
            return items;

        var hasDivider = false;
        for (var protocol in editorProtocols)
        {
            if (!eps.externalProtocolHandlerExists(protocol))
                continue;

            if (!hasDivider)
            {
                items.push("-");
                hasDivider = true;
            }

            items.push({
                label: stringBundle.getString("OpenIn" + editorProtocols[protocol]),
                command: bindFixed(this.openInEditor, this, protocol, sourceLink)
            });
        }

        return items;
    };

    sl.openInEditor = function(protocol, sourceLink) {
        var url = protocol + "://open?";
        url += "url=" + encodeURIComponent(sourceLink.lessDebugInfo["filename"]);

        if (sourceLink.lessDebugInfo["line"])
            url += "&line=" + sourceLink.lessDebugInfo["line"];

        window.location = url;
    };
}});
