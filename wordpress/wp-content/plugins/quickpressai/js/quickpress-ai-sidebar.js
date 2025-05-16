const { registerPlugin } = window.wp.plugins;
const { PluginDocumentSettingPanel } = window.wp.editPost;
const { createElement, useState, useEffect, useRef, createPortal } = window.wp.element;
const { Button, TextareaControl, Notice, Spinner, Icon, Modal } = window.wp.components;
const { select, dispatch } = window.wp.data;

registerPlugin("quickpress-ai-sidebar", {
  render: () => {
    const [titlePrompt, setTitlePrompt] = useState("");
    const [contentPrompt, setContentPrompt] = useState("");
    const [excerptPrompt, setExcerptPrompt] = useState("");
    const [isProcessingTitle, setIsProcessingTitle] = useState(false);
    const [isProcessingContent, setIsProcessingContent] = useState(false);
    const [isProcessingExcerpt, setIsProcessingExcerpt] = useState(false);
    const [isTitleComplete, setIsTitleComplete] = useState(false);
    const [isContentComplete, setIsContentComplete] = useState(false);
    const [isExcerptComplete, setIsExcerptComplete] = useState(false);
    const [errorMessage, setErrorMessage] = useState(null);
    const apiKeySet = QuickPressAIEditor.apiKeySet;
    const aiModelSet = QuickPressAIEditor.aiModelSet;

    const resetCompletionStates = () => {
      setIsTitleComplete(false);
      setIsContentComplete(false);
      setIsExcerptComplete(false);
    };

    const loadTemplate = (field) => {
      if (field === "title") {
        setTitlePrompt(QuickPressAIEditor.titlePromptTemplate || "");
      } else if (field === "content") {
        setContentPrompt(QuickPressAIEditor.contentPromptTemplate || "");
      } else if (field === "excerpt") {
        setExcerptPrompt(QuickPressAIEditor.excerptPromptTemplate || "");
      }
    };

    const processTitle = async () => {
      resetCompletionStates();
      setIsProcessingTitle(true);
      setErrorMessage(null);
      const title = select("core/editor").getEditedPostAttribute("title");
      try {
        const response = await fetch(QuickPressAIEditor.ajaxUrl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            action: "quickpress_ai_rewrite_title",
            nonce: QuickPressAIEditor.nonce,
            title: title,
            prompt: titlePrompt,
          }),
        });
        const result = await response.json();
        setIsProcessingTitle(false);
        if (result.success) {
          dispatch("core/editor").editPost({ title: result.data.rewrittenTitle });
          setIsTitleComplete(true);
        } else {
          setErrorMessage(result.data || "Failed to process title.");
        }
      } catch (error) {
        setIsProcessingTitle(false);
        setErrorMessage("An unexpected error occurred while rewriting the title.");
        if (QuickPressAIEditor.debug) {
           console.error(error);
        }
      }
    };

    const processContent = async () => {
      resetCompletionStates();
      setIsProcessingContent(true);
      setErrorMessage(null);
      const content = select("core/editor").getEditedPostAttribute("content");
      try {
        const response = await fetch(QuickPressAIEditor.ajaxUrl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            action: "quickpress_ai_add_to_content",
            nonce: QuickPressAIEditor.nonce,
            content: content,
            user_prompt: contentPrompt,
          }),
        });
        const result = await response.json();
        setIsProcessingContent(false);
        if (result.success) {
          const updatedContent = result.data?.updatedContent || "";
          let parsedContent = wp.blocks.parse(updatedContent);
          if (!parsedContent.length) {
            parsedContent = [wp.blocks.createBlock("core/paragraph", { content: updatedContent, })];
          }
          dispatch("core/editor").resetBlocks(parsedContent);
          setIsContentComplete(true);
        } else {
          setErrorMessage(result.data || "Failed to process content.");
        }
      } catch (error) {
        setIsProcessingContent(false);
        setErrorMessage("An unexpected error occurred while processing the content.");
        if (QuickPressAIEditor.debug) {
            console.error("Fetch error:", error);
        }
      }
    };

    const processExcerpt = async () => {
      resetCompletionStates();
      setIsProcessingExcerpt(true);
      setErrorMessage(null);
      try {
        const response = await fetch(QuickPressAIEditor.ajaxUrl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            action: "quickpress_ai_generate_excerpt",
            nonce: QuickPressAIEditor.nonce,
            user_prompt: excerptPrompt,
          }),
        });
        const result = await response.json();
        setIsProcessingExcerpt(false);
        if (result.success) {
          const updatedExcerpt = result.data?.updatedExcerpt || "";
          dispatch("core/editor").editPost({ excerpt: updatedExcerpt });
          setIsExcerptComplete(true);
        } else {
          setErrorMessage(result.data || "Failed to generate excerpt.");
        }
      } catch (error) {
        setIsProcessingExcerpt(false);
        setErrorMessage("An unexpected error occurred while generating the excerpt.");
        if (QuickPressAIEditor.debug) {
            console.error("Fetch error:", error);
        }
      }
    };

    if (!apiKeySet || !aiModelSet) {
      return createElement(
        PluginDocumentSettingPanel,
        { name: "quickpress-ai-panel", title: "QuickPress AI" },
        createElement(
          Notice,
          { status: "warning", isDismissible: false },
          "API key and AI Model required on the ",
          createElement(
            "a",
            { href: "/wp-admin/options-general.php?page=quickpress-ai-settings", style: { color: "#007cba", textDecoration: "underline" } },
            "plugin settings page"
          ),
          "."
        )
      );
    }

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [aiContent, setAiContent] = useState('');
    const [customPrompt, setCustomPrompt] = useState('');
    const editorId = 'quickpress-ai-editor';
    const [forceUpdate, setForceUpdate] = useState(false);
    const [buttonPosition, setButtonPosition] = useState({ top: 0, left: 0, visible: false });
    const selectedTextRef = useRef("");
    const [selectedText, setSelectedText] = useState(selectedTextRef.current || "");
    const selectionRangeRef = useRef(null);
    const { getSelectedBlockClientId, getSelectedBlock } = select("core/block-editor");
    const { updateBlockAttributes } = dispatch("core/block-editor");
    const [formatRefinedContent, setFormatRefinedContent] = useState(false);

    const [isProcessingAI, setIsProcessingAI] = useState(false);
    const [isAIComplete, setIsAIComplete] = useState(false);

    const checkboxId = "formatRefinedContentCheckbox";

    useEffect(() => {
        if (isModalOpen && selectionRangeRef.current) {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(selectionRangeRef.current);
            if (QuickPressAIEditor.debug) {
                console.log("Selection restored in content editor.");
            }
        }
    }, [isModalOpen]);

    const handleSelection = () => {
        setTimeout(() => {
            const selection = window.getSelection();

            if (!selection.rangeCount || selection.toString().trim() === "") {
                setButtonPosition({ top: 0, left: 0, visible: false });
                return;
            }

            const range = selection.getRangeAt(0);
            const selectedElement = range.commonAncestorContainer;

            const isInsideModal = document.querySelector(".quickpress-modal")?.contains(selectedElement);
            if (isInsideModal) {
                if (QuickPressAIEditor.debug) {
                    console.log("Ignoring selection inside modal.");
                }
                return;
            }

            selectionRangeRef.current = range;
            selectedTextRef.current = selection.toString();
            setSelectedText(selectedTextRef.current);
            if (QuickPressAIEditor.debug) {
                console.log("Stored selected text:", selectedTextRef.current);
            }

            const rect = range.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                const newPosition = {
                    top: window.scrollY + rect.top + rect.height / 2,
                    left: 10,
                    visible: true
                };
                setButtonPosition(newPosition);
            } else {
                setButtonPosition({ top: 0, left: 0, visible: false });
            }
        }, 10);
    };

    useEffect(() => {
        const handleSelectionEvent = () => {
            setTimeout(handleSelection, 10);
        };

        document.addEventListener("mouseup", handleSelectionEvent);
        document.addEventListener("keyup", handleSelectionEvent);

        return () => {
            document.removeEventListener("mouseup", handleSelectionEvent);
            document.removeEventListener("keyup", handleSelectionEvent);
        };
    }, []);

    const restoreSelection = () => {
      if (!selectionRangeRef.current) return;
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(selectionRangeRef.current);
    };

    const handleAIReplacement = async () => {
        if (!selectedText.trim()) {
            setErrorMessage("Please select text before refining.");
            return;
        }

        restoreSelection();
        setIsProcessingAI(true);
        setIsAIComplete(false);
        setErrorMessage(null);

        try {
            const response = await fetch(ajaxurl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    action: "quickpress_ai_refine_inline",
                    nonce: QuickPressAIEditor.nonce,
                    content: selectedText,
                    user_prompt: customPrompt,
                    format_content: formatRefinedContent ? "true" : "false",
                }),
            });

            const result = await response.json();
            setIsProcessingAI(false);

            if (!result.success) {
                setErrorMessage(result.data || "An error occurred while processing.");
                return;
            }

            let generatedContent = result.data.updatedContent;
            setAiContent(generatedContent);
            setIsAIComplete(true);
        } catch (error) {
            if (QuickPressAIEditor.debug) {
                console.error("Fetch error:", error);
            }
            setErrorMessage("An unexpected error occurred.");
            setIsProcessingAI(false);
        }
    };

    const handleReplaceText = () => {
      const selectedBlockId = getSelectedBlockClientId();
      const selectedBlock = getSelectedBlock(selectedBlockId);
      if (!selectedBlock) return;
      const tinymceEditor = window.tinymce.get(editorId);
      if (!tinymceEditor) return;
      const updatedText = tinymceEditor.getContent();
      if (!selectedTextRef.current) return;
      const updatedContent = selectedBlock.attributes.content.replace(selectedTextRef.current, updatedText);
      updateBlockAttributes(selectedBlockId, { content: updatedContent });
      setIsModalOpen(false);
      setCustomPrompt('');
      setAiContent('');
      setButtonPosition({ top: 0, left: 0, visible: false });
    };

    if (!document.getElementById("quickpress-tooltip-style")) {
        const style = document.createElement("style");
        style.id = "quickpress-tooltip-style";
        style.innerHTML = `
            #quickpress-ai-tooltip::after {
                content: "";
                position: absolute;
                top: -12px; /* Position below tooltip */
                left: 50%;
                transform: translateX(-50%);
                border-width: 6px;
                border-style: solid;
                border-color: rgba(0, 0, 0, 0.8) transparent; /* Creates the arrow */
            }
        `;
        document.head.appendChild(style);
    }

    useEffect(() => {
        if (buttonPosition.visible) {
            let button = document.getElementById("quickpress-ai-floating-button");
            let tooltip = document.getElementById("quickpress-ai-tooltip");

            if (!button) {
                button = document.createElement("img");
                button.id = "quickpress-ai-floating-button";
                button.src = QuickPressAIEditor.logoUrl;
                button.alt = "QuickPress AI Refine Inline";

                button.style.position = "absolute";
                button.style.width = "64px";
                button.style.height = "64px";
                button.style.borderRadius = "50%";
                button.style.objectFit = "cover";
                button.style.cursor = "pointer";
                button.style.zIndex = "9999";
                button.style.boxShadow = "0px 4px 6px rgba(0,0,0,0.1)";

                const rect = document.getSelection().getRangeAt(0).getBoundingClientRect();
                button.style.top = `${buttonPosition.top}px`;
                button.style.left = "100px";

                if (!tooltip) {
                    tooltip = document.createElement("div");
                    tooltip.id = "quickpress-ai-tooltip";
                    tooltip.innerText = "QuickPress AI Refine Inline";
                    tooltip.style.position = "absolute";
                    tooltip.style.transform = "translateX(-50%)";
                    tooltip.style.background = "rgba(0, 0, 0, 0.8)";
                    tooltip.style.color = "#fff";
                    tooltip.style.padding = "6px 8px";
                    tooltip.style.borderRadius = "4px";
                    tooltip.style.fontSize = "12px";
                    tooltip.style.whiteSpace = "nowrap";
                    tooltip.style.visibility = "hidden";
                    tooltip.style.opacity = "0";
                    tooltip.style.transition = "opacity 0.1s ease-in-out";
                    document.body.appendChild(tooltip);
                }

                button.onmouseover = () => {
                    tooltip.style.visibility = "visible";
                    tooltip.style.opacity = "1";
                    tooltip.style.top = `${button.getBoundingClientRect().bottom + 8}px`;
                    tooltip.style.left = `${button.getBoundingClientRect().left + button.offsetWidth / 2}px`;
                    tooltip.style.transform = "translateX(-50%)";
                };

                button.onmouseleave = () => {
                    tooltip.style.visibility = "hidden";
                    tooltip.style.opacity = "0";
                };

                button.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    restoreSelection();
                    setIsModalOpen(true);
                };

                document.body.appendChild(button);
            } else {
                const rect = document.getSelection().getRangeAt(0).getBoundingClientRect();
                button.style.left = "100px";
                button.style.top = `${buttonPosition.top}px`;
            }
        } else {
            let button = document.getElementById("quickpress-ai-floating-button");
            let tooltip = document.getElementById("quickpress-ai-tooltip");

            if (button) button.remove();
            if (tooltip) tooltip.remove();
        }
    }, [buttonPosition]);

    const sidebarPanel = createElement(
        PluginDocumentSettingPanel,
        { name: "quickpress-ai-panel", title: "QuickPress AI" },
        errorMessage &&
            createElement(
                Notice,
                { status: "error", onRemove: () => setErrorMessage(null) },
                errorMessage
            ),
        createElement(
            "div",
            { className: "input-section" },
            createElement(
                "p",
                { style: { fontWeight: "bold", display: "flex", alignItems: "center", gap: "5px" } },
                "Title",
                createElement(
                    "a",
                    {
                        href: "#",
                        style: { fontSize: "12px", color: "#007cba" },
                        onClick: (e) => {
                            e.preventDefault();
                            loadTemplate("title");
                        },
                    },
                    "(load template)"
                )
            ),
            createElement(TextareaControl, {
                value: titlePrompt,
                onChange: (value) => setTitlePrompt(value),
                disabled: isProcessingTitle,
                placeholder: "Enter instructions to generate a title",
            }),
            createElement(
                "div",
                { style: { display: "flex", alignItems: "center", gap: "10px", marginBottom: "20px" } },
                createElement(Button, {
                    isPrimary: true,
                    onClick: processTitle,
                    disabled: isProcessingTitle || !titlePrompt,
                },
                    isProcessingTitle ? "Generating..." : "Submit"
                ),
                isProcessingTitle ? createElement(Spinner) : isTitleComplete && createElement(Icon, { icon: "yes", style: { color: "green" } })
            )
        ),
        createElement(
            "div",
            { className: "input-section" },
            createElement(
                "p",
                { style: { fontWeight: "bold", display: "flex", alignItems: "center", gap: "5px" } },
                "Page/Post Content",
                createElement(
                    "a",
                    {
                        href: "#",
                        style: { fontSize: "12px", color: "#007cba" },
                        onClick: (e) => {
                            e.preventDefault();
                            loadTemplate("content");
                        },
                    },
                    "(load template)"
                )
            ),
            createElement(TextareaControl, {
                value: contentPrompt,
                onChange: (value) => setContentPrompt(value),
                disabled: isProcessingContent,
                placeholder: "Enter instructions to generate new content and add it to any existing page/post content",
            }),
            createElement(
                "div",
                { style: { display: "flex", alignItems: "center", gap: "10px", marginBottom: "20px" } },
                createElement(Button, {
                    isPrimary: true,
                    onClick: processContent,
                    disabled: isProcessingContent || !contentPrompt,
                },
                    isProcessingContent ? "Generating..." : "Submit"
                ),
                isProcessingContent ? createElement(Spinner) : isContentComplete && createElement(Icon, { icon: "yes", style: { color: "green" } })
            )
        ),
        createElement(
            "div",
            { className: "input-section" },
            createElement(
                "p",
                { style: { fontWeight: "bold", display: "flex", alignItems: "center", gap: "5px" } },
                "Excerpt",
                createElement(
                    "a",
                    {
                        href: "#",
                        style: { fontSize: "12px", color: "#007cba" },
                        onClick: (e) => {
                            e.preventDefault();
                            loadTemplate("excerpt");
                        },
                    },
                    "(load template)"
                )
            ),
            createElement(TextareaControl, {
                value: excerptPrompt,
                onChange: (value) => setExcerptPrompt(value),
                disabled: isProcessingExcerpt,
                placeholder: "Enter instructions to generate an excerpt",
            }),
            createElement(
                "div",
                { style: { display: "flex", alignItems: "center", gap: "10px" } },
                createElement(Button, {
                    isPrimary: true,
                    onClick: processExcerpt,
                    disabled: isProcessingExcerpt || !excerptPrompt,
                },
                    isProcessingExcerpt ? "Generating..." : "Submit"
                ),
                isProcessingExcerpt ? createElement(Spinner) : isExcerptComplete && createElement(Icon, { icon: "yes", style: { color: "green" } })
            )
        )
    );

    const modalElement = isModalOpen
    ? createPortal(
        createElement(
          Modal,
            {
              className: "quickpress-modal",
              title: "QuickPress AI Refine Inline",
              onRequestClose: () => {
                  setIsModalOpen(false);
                  setForceUpdate((prev) => !prev);
              },
              style: {
                width: window.innerWidth < 768 ? "100vw" : "60vw",
                height: window.innerWidth < 768 ? "75vh" : "80vh",
                maxWidth: window.innerWidth < 768 ? "100vw" : "60vw",
                maxHeight: window.innerWidth < 768 ? "75vh" : "80vh" }
            },
            createElement(
                "div",
                { style: { display: "flex", gap: "20px" } },
                createElement(
                    "div",
                    { style: { flex: "1" } },
                    createElement("p", { style: { fontWeight: "bold", marginBottom: "5px" } }, "Instructions"),
                    createElement(TextareaControl, {
                        value: customPrompt,
                        onChange: (value) => setCustomPrompt(value),
                        placeholder: "How would you like the selected text refined?",
                    })
                ),
                createElement(
                    "div",
                    { style: { flex: "1" } },
                    createElement("p", { style: { fontWeight: "bold", marginBottom: "5px" } }, "Selected Text"),
                    createElement(TextareaControl, {
                        value: selectedText,
                        onChange: (value) => setSelectedText(value),
                        placeholder: "No text selected",
                    })
                )
            ),
            createElement(
                "label",
                {
                    htmlFor: checkboxId,
                    style: { display: "flex", alignItems: "center", gap: "6px", marginTop: "10px", marginBottom: "10px", cursor: "pointer" }
                },
                createElement("input", {
                    id: checkboxId,
                    type: "checkbox",
                    checked: formatRefinedContent,
                    onChange: (e) => setFormatRefinedContent(e.target.checked),
                    style: { width: "16px", height: "16px", cursor: "pointer" }
                }),
                createElement("span", { style: { fontSize: "14px", lineHeight: "14px" } }, "Apply formatting to refined content"),
                createElement("a", {
                    href: QuickPressAIEditor.quickpressUrl ? `${QuickPressAIEditor.quickpressUrl}/docs/#RefineInline` : "#",
                    target: "_blank",
                    rel: "noopener noreferrer",
                    onClick: (e) => e.stopPropagation(),
                    style: { fontSize: "14px", lineHeight: "14px", color: "#0073aa", marginLeft: "0px", textDecoration: "underline", cursor: "pointer" }
                }, "(learn more)")
            ),
            errorMessage &&
                  createElement(
                      "div",
                      { style: { color: "red", fontWeight: "bold", marginBottom: "10px" } },
                      errorMessage
                  ),
            createElement(
                "div",
                { style: { display: "flex", alignItems: "center", gap: "10px", marginTop: ".5rem", marginBottom: "1rem" } },
                createElement(Button, {
                    isPrimary: true,
                    onClick: handleAIReplacement,
                    disabled: isProcessingAI,
                }, isProcessingAI ? "Generating..." : "Submit"),
                isProcessingAI ? createElement(Spinner) : isAIComplete && createElement(Icon, { icon: "yes", style: { color: "green" } })
            ),
            createElement(
                "div",
                null,
                createElement("textarea", { id: "editor-ai-content" })
            )
        ),
        document.body
    )
    : null;

    useEffect(() => {
        if (isModalOpen) {
            setTimeout(() => {
                if (window.tinymce) {
                    window.tinymce.remove("#editor-ai-content");
                    window.tinymce.init({
                        selector: "#editor-ai-content",
                        height: 225,
                        menubar: false,
                        plugins: "link lists",
                        toolbar: "formatselect | bold italic | bullist numlist | link",
                        branding: false,
                        block_formats: "Paragraph=p; Header 1=h1; Header 2=h2; Header 3=h3; Header 4=h4; Header 5=h5; Header 6=h6; Preformatted=pre",
                        setup: (editor) => {
                            editor.on("init", () => {
                                if (!aiContent) {
                                    editor.setContent('<p style="color: #aaa;">Refined content generated here...</p>');
                                } else {
                                    editor.setContent(aiContent);
                                }
                            });

                            editor.on("focus", () => {
                                if (editor.getContent().includes("Refined content generated here...")) {
                                    editor.setContent("");
                                }
                            });

                            editor.on("blur", () => {
                                if (editor.getContent().trim() === "") {
                                    editor.setContent('<p style="color: #aaa;">Refined content generated here...</p>');
                                }
                            });
                        },
                    });
                }
            }, 500);
        }
    }, [isModalOpen, aiContent]);

    return createElement("div", null, sidebarPanel, modalElement);
  }
});
