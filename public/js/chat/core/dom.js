/**
 * DOM utilities:
 * - el(tag, className?, text?) quick element builder
 * - elements: lazy getters for key app nodes (IDs are centralized here)
 */
export const el = (tag, cls = '', text = '') => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
};

export const elements = {
  get chatListEl() { return document.getElementById('chatList'); },
  get modelSelect() { return document.getElementById('modelSelect'); },
  get messagesEl() { return document.getElementById('messages'); },
  get composer() { return document.getElementById('composer'); },
  get newChatBtn() { return document.getElementById('newChatBtn'); },
  get sendBtn() { return document.getElementById('sendBtn'); },
  get sidebar() { return document.getElementById('sidebar'); },
  get divider() { return document.getElementById('sidebarDivider'); },
  get sidebarToggle() { return document.getElementById('sidebarToggle'); },
  get sidebarIcon() { return document.getElementById('sidebarIcon'); },
  get themeToggle() { return document.getElementById('themeToggle'); },
};
