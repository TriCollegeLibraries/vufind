<!-- available fields are defined in solr/biblio/conf/schema.xml -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:qdc="http://epubs.cclrc.ac.uk/xmlns/qdc/" 
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:php="http://php.net/xsl"
    xmlns:xlink="http://www.w3.org/2001/XMLSchema-instance">
    <xsl:output method="xml" indent="yes" encoding="utf-8"/>
    <xsl:param name="language">CDM</xsl:param>
    <xsl:template match="qdc:qualifieddc">
        <add>
            <doc>
                <!-- ID -->
                <!-- Important: This relies on an <identifier> tag being injected by the OAI-PMH harvester. -->
                <field name="id">
                    <xsl:value-of select="//identifier"/>
                </field>

                <!-- RECORDTYPE -->
                <field name="recordtype">contentdm</field>

                <!-- FULLRECORD -->
                <!-- disabled for now; records are so large that they cause memory problems!
                <field name="fullrecord">
                    <xsl:copy-of select="php:function('VuFind::xmlAsText', //oai_dc:dc)"/>
                </field>
                  -->

                <!-- ALLFIELDS -->
                <field name="allfields">
                    <xsl:value-of select="normalize-space(string(//qdc:qualifieddc))"/>
                </field>

               <!-- SET LANGUAGE UNIQUELY TO CDM -->	
               <field name="language">
                   <xsl:value-of select="$language" />
               </field>

               <!-- INSTITUTION, DEPARTMENT, AND COLLECTION -->
                <xsl:if test="//dcterms:isPartOf">
                    <xsl:for-each select="//dcterms:isPartOf">
                        <xsl:if test="position()=1">
                            <xsl:call-template name="noSemis">
                                <xsl:with-param name="fieldName">
                                    <xsl:text>institution</xsl:text>
                                </xsl:with-param>
                                <xsl:with-param name="contents">
                                    <xsl:value-of select="normalize-space()" />
                                </xsl:with-param>
                            </xsl:call-template>
                        </xsl:if>
                        <xsl:if test="position()=2">
                            <xsl:call-template name="noSemis">
                                <xsl:with-param name="fieldName">
                                    <xsl:text>department</xsl:text>
                                </xsl:with-param>
                                <xsl:with-param name="contents">
                                    <xsl:value-of select="normalize-space()" />
                                </xsl:with-param>
                            </xsl:call-template>
                        </xsl:if>
                        <xsl:if test="position()>2">
                            <xsl:call-template name="noSemis">
                                <xsl:with-param name="fieldName">
                                    <xsl:text>collection</xsl:text>
                                </xsl:with-param>
                                <xsl:with-param name="contents">
                                    <xsl:value-of select="normalize-space()" />
                                </xsl:with-param>
                            </xsl:call-template>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- SUBJECT -->
                <xsl:if test="//dc:subject">
                    <xsl:for-each select="//dc:subject">
                        <xsl:if test="string-length() > 0">
                            <xsl:call-template name="noSemis">
                                <xsl:with-param name="fieldName">
                                    <xsl:text>topic_facet</xsl:text>
                                </xsl:with-param>
                                <xsl:with-param name="contents">
                                    <xsl:value-of select="normalize-space()" />
                                </xsl:with-param>
                            </xsl:call-template>
                            <xsl:call-template name="noSemis">
                                <xsl:with-param name="fieldName">
                                    <xsl:text>topic</xsl:text>
                                </xsl:with-param>
                                <xsl:with-param name="contents">
                                    <xsl:value-of select="normalize-space()" />
                                </xsl:with-param>
                            </xsl:call-template>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- AUTHOR -->
                <xsl:if test="//dc:creator">
                    <xsl:for-each select="//dc:creator">
                        <xsl:if test="normalize-space()">
                            <!-- author is not a multi-valued field, so we'll put
                                 first value there and subsequent values in author2.
                            -->
                            <xsl:if test="position()=1">
                                <xsl:call-template name="noSemis">
                                    <xsl:with-param name="fieldName">
                                        <xsl:text>author</xsl:text>
                                    </xsl:with-param>
                                    <xsl:with-param name="contents">
                                        <xsl:value-of select="normalize-space()" />
                                    </xsl:with-param>
                                </xsl:call-template>
                                <xsl:call-template name="noSemis">
                                    <xsl:with-param name="fieldName">
                                        <xsl:text>authorSort</xsl:text>
                                    </xsl:with-param>
                                    <xsl:with-param name="contents">
                                        <xsl:value-of select="normalize-space()" />
                                    </xsl:with-param>
                                </xsl:call-template>
                                <xsl:call-template name="noSemis">
                                    <xsl:with-param name="fieldName">
                                        <xsl:text>cdm_author_facet</xsl:text>
                                    </xsl:with-param>
                                    <xsl:with-param name="contents">
                                        <xsl:value-of select="normalize-space()" />
                                    </xsl:with-param>
                                </xsl:call-template>
                            </xsl:if>
                            <xsl:if test="position()>1">
                                <xsl:call-template name="noSemis">
                                    <xsl:with-param name="fieldName">
                                        <xsl:text>author2</xsl:text>
                                    </xsl:with-param>
                                    <xsl:with-param name="contents">
                                        <xsl:value-of select="normalize-space()" />
                                    </xsl:with-param>
                                </xsl:call-template>
                                <xsl:call-template name="noSemis">
                                    <xsl:with-param name="fieldName">
                                        <xsl:text>cdm_author_facet</xsl:text>
                                    </xsl:with-param>
                                    <xsl:with-param name="contents">
                                        <xsl:value-of select="normalize-space()" />
                                    </xsl:with-param>
                                </xsl:call-template>
                            </xsl:if>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- TITLE -->
                <xsl:if test="//dc:title[normalize-space()]">
                    <field name="title">
                        <xsl:value-of select="//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_short">
                        <xsl:value-of select="//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_full">
                        <xsl:value-of select="//dc:title[normalize-space()]"/>
                    </field>
                    <field name="title_sort">
                        <xsl:value-of select="php:function('VuFind::stripArticles', string(//dc:title[normalize-space()]))"/>
                    </field>
                </xsl:if>

                <!-- FORMAT -->
                <xsl:if test="//dc:format">
                    <xsl:for-each select="//dc:format">
                        <xsl:if test="string-length() > 0">
                            <xsl:call-template name="noSemis">
                                <xsl:with-param name="fieldName">
                                    <xsl:text>format</xsl:text>
                                </xsl:with-param>
                                <xsl:with-param name="contents">
                                    <xsl:value-of select="normalize-space()" />
                                </xsl:with-param>
                            </xsl:call-template>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- DESCRIPTION -->
                <xsl:if test="//dc:description">
                    <xsl:for-each select="//dc:description">
                        <xsl:if test="string-length() > 0">
                            <field name="cdm_description">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- CREATION DATE -->
                <xsl:if test="//dcterms:created">
                    <xsl:for-each select="//dcterms:created">
                        <xsl:if test="string-length() > 0">
                            <field name="creation_date">
                                <xsl:value-of select="normalize-space()"/>
                            </field>
                        </xsl:if>
                    </xsl:for-each>
                </xsl:if>

                <!-- URL -->
               <xsl:for-each select="//dc:identifier">
                   <xsl:if test="substring(., 1, 31) = &quot;http://triptych.brynmawr.edu:81&quot;">
        		<xsl:variable name="cdmID" select='substring-after(.,"http://triptych.brynmawr.edu:81/u?/")'/>
                	<xsl:variable name="cdmCollection" select='substring-before($cdmID,",")'/>
                	<xsl:variable name="cdmNumber" select='substring-after($cdmID,",")'/>
                        <field name="url">http://triptych.brynmawr.edu/u?/<xsl:value-of select='$cdmCollection'/><xsl:text>, </xsl:text><xsl:value-of select='$cdmNumber'/>
                        </field>

                	<field name="thumbnail">http://triptych.brynmawr.edu/utils/getthumbnail/collection/<xsl:value-of select='$cdmCollection'/><xsl:text>/id/</xsl:text><xsl:value-of select='$cdmNumber'/>
                	</field>
                   </xsl:if>
               </xsl:for-each>
            </doc>
        </add>
    </xsl:template>

    <!-- LOOP TO OMIT SEMICOLINS -->
    <xsl:template name="noSemis">
        <xsl:param name="fieldName"></xsl:param>
        <xsl:param name="contents"></xsl:param>
        <xsl:choose>
            <xsl:when test="contains($contents, ';')">
                <field name="{$fieldName}">
                    <xsl:value-of select="substring-before($contents, ';')" />
                </field>
                <xsl:if test="($fieldName != 'authorSort' and string-length(substring-after($contents, ';')) > 0)">
                    <xsl:call-template name="noSemis">
                        <xsl:with-param name="fieldName">
                            <xsl:choose>
                                <xsl:when test="$fieldName = 'author'">
                                    <xsl:text>author2</xsl:text>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="$fieldName" />
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:with-param>
                        <xsl:with-param name="contents">
                            <xsl:value-of select="substring-after($contents, '; ')" />
                        </xsl:with-param>
                     </xsl:call-template>
                 </xsl:if>
            </xsl:when>
            <xsl:otherwise>
                <field name="{$fieldName}">
                    <xsl:value-of select="$contents" />
                </field>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
</xsl:stylesheet>
