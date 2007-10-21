<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/result">
result=<xsl:value-of select="@outcome"/>
description=<xsl:value-of select="."/>
</xsl:template>

</xsl:stylesheet>
