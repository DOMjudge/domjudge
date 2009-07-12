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

<xsl:template match="/root/scoreboard"><!-- xml:space="preserve" -->
  <xsl:for-each select="rows/row">
    <xsl:value-of select="@rank"/><xsl:call-template name="fieldsep"/>
    <xsl:value-of select="num_solved"/><xsl:call-template name="fieldsep"/>
    <xsl:value-of select="totaltime"/><xsl:call-template name="fieldsep"/>
    <xsl:value-of select="team"/>
    <xsl:call-template name="recordsep"/>
  </xsl:for-each>
</xsl:template>

</xsl:stylesheet>
