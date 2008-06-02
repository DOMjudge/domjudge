<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="text" encoding="utf-8" omit-xml-declaration="yes" indent="no"/>

<!-- Predefined CVS field and record separators -->
<xsl:template name="fieldsep">
  <xsl:text>|</xsl:text>
</xsl:template>
<xsl:template name="recordsep">
  <xsl:text>
</xsl:text>
</xsl:template>

<xsl:template match="/root/events"><!-- xml:space="preserve" -->
  <xsl:for-each select="event">
    <xsl:value-of select="@id"/><xsl:call-template name="fieldsep"/>
    <xsl:call-template name="recordsep"/>
  </xsl:for-each>
</xsl:template>

</xsl:stylesheet>
