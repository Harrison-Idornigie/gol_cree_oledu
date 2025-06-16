"use client";

import React, { useState, ReactNode, useRef, useEffect } from "react";
import { createPortal } from "react-dom";

interface TooltipProps {
  children: ReactNode;
  content: ReactNode;
  side?: "top" | "right" | "bottom" | "left";
  className?: string;
}

const Tooltip = ({ children, content, side = "top", className = "" }: TooltipProps) => {
  const [isVisible, setIsVisible] = useState(false);
  const [timeoutId, setTimeoutId] = useState<NodeJS.Timeout | null>(null);
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const triggerRef = useRef<HTMLDivElement>(null);

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  }, [timeoutId]);

  const showTooltip = () => {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
    const id = setTimeout(() => {
      if (triggerRef.current) {
        const rect = triggerRef.current.getBoundingClientRect();
        const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;

        let x = 0;
        let y = 0;

        switch (side) {
          case "right":
            x = rect.right + scrollX + 8; // 8px offset
            y = rect.top + scrollY + rect.height / 2;
            break;
          case "left":
            x = rect.left + scrollX - 8; // 8px offset
            y = rect.top + scrollY + rect.height / 2;
            break;
          case "bottom":
            x = rect.left + scrollX + rect.width / 2;
            y = rect.bottom + scrollY + 8; // 8px offset
            break;
          case "top":
          default:
            x = rect.left + scrollX + rect.width / 2;
            y = rect.top + scrollY - 8; // 8px offset
            break;
        }

        setPosition({ x, y });
        setIsVisible(true);
      }
    }, 300); // 300ms delay
    setTimeoutId(id);
  };

  const hideTooltip = () => {
    if (timeoutId) {
      clearTimeout(timeoutId);
      setTimeoutId(null);
    }
    setIsVisible(false);
  };

  const getTooltipClasses = () => {
    const baseClasses = "fixed z-[99999] px-3 py-2 text-sm text-white bg-gray-900 rounded-md shadow-xl whitespace-nowrap pointer-events-none transition-opacity duration-200";

    switch (side) {
      case "right":
        return `${baseClasses} transform -translate-y-1/2`;
      case "left":
        return `${baseClasses} transform -translate-y-1/2 -translate-x-full`;
      case "bottom":
        return `${baseClasses} transform -translate-x-1/2`;
      case "top":
      default:
        return `${baseClasses} transform -translate-x-1/2 -translate-y-full`;
    }
  };

  const getArrowClasses = () => {
    const baseArrowClasses = "absolute w-2 h-2 bg-gray-900 transform rotate-45";

    switch (side) {
      case "right":
        return `${baseArrowClasses} -left-1 top-1/2 -translate-y-1/2`;
      case "left":
        return `${baseArrowClasses} -right-1 top-1/2 -translate-y-1/2`;
      case "bottom":
        return `${baseArrowClasses} -top-1 left-1/2 -translate-x-1/2`;
      case "top":
      default:
        return `${baseArrowClasses} -bottom-1 left-1/2 -translate-x-1/2`;
    }
  };

  return (
    <>
      <div className="relative inline-block">
        <div
          ref={triggerRef}
          onMouseEnter={showTooltip}
          onMouseLeave={hideTooltip}
          onFocus={showTooltip}
          onBlur={hideTooltip}
          className="w-full"
        >
          {children}
        </div>
      </div>
      {isVisible && typeof document !== 'undefined' && createPortal(
        <div
          className={`${getTooltipClasses()} ${className}`}
          style={{
            left: position.x,
            top: position.y,
          }}
        >
          {content}
          <div className={getArrowClasses()}></div>
        </div>,
        document.body
      )}
    </>
  );
};

// These are just placeholders to maintain API compatibility with the code that uses these components
const TooltipProvider = ({ children }: { children: ReactNode }) => (
  <>{children}</>
);
const TooltipTrigger = ({
  children,
  ...props
}: {
  children: ReactNode;
  [key: string]: unknown;
}) => <div {...props}>{children}</div>;
const TooltipContent = ({ children }: { children: ReactNode }) => (
  <>{children}</>
);

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider };
