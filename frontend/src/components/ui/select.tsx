"use client";

import * as React from "react";
import { Check, ChevronDown, ChevronUp } from "lucide-react";
import { cn } from "@/lib/utils";

interface SelectProps {
  value?: string;
  defaultValue?: string;
  onValueChange?: (value: string) => void;
  open?: boolean;
  defaultOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  children?: React.ReactNode;
  disabled?: boolean;
  name?: string;
}

const Select = React.forwardRef<HTMLDivElement, SelectProps>(
  ({ children, onValueChange, defaultValue, value, ...props }, ref) => {
    const [selectedValue, setSelectedValue] = React.useState(
      defaultValue || value || ""
    );
    const [isOpen, setIsOpen] = React.useState(false);

    React.useEffect(() => {
      if (value !== undefined) {
        setSelectedValue(value);
      }
    }, [value]);

    const handleValueChange = (newValue: string) => {
      setSelectedValue(newValue);
      onValueChange?.(newValue);
      setIsOpen(false);
    };

    return (
      <div ref={ref} className="relative">
        {React.Children.map(children, (child) => {
          if (React.isValidElement(child)) {
            return React.cloneElement(child as React.ReactElement<any>, {
              selectedValue,
              isOpen,
              setIsOpen,
              onValueChange: handleValueChange,
            });
          }
          return child;
        })}
      </div>
    );
  }
);
Select.displayName = "Select";

const SelectGroup = ({
  children,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) => {
  return <div {...props}>{children}</div>;
};
SelectGroup.displayName = "SelectGroup";

const SelectValue = ({
  children,
  placeholder,
}: {
  children?: React.ReactNode;
  placeholder?: string;
}) => {
  return <>{children || placeholder}</>;
};
SelectValue.displayName = "SelectValue";

interface SelectTriggerProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  selectedValue?: string;
  isOpen?: boolean;
  setIsOpen?: (open: boolean) => void;
}

const SelectTrigger = React.forwardRef<HTMLButtonElement, SelectTriggerProps>(
  (
    { className, children, selectedValue, isOpen, setIsOpen, ...props },
    ref
  ) => {
    return (
      <button
        ref={ref}
        type="button"
        onClick={() => setIsOpen?.(!isOpen)}
        className={cn(
          "flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50",
          className
        )}
        {...props}
      >
        {children}
        <ChevronDown className="w-4 h-4 opacity-50" />
      </button>
    );
  }
);
SelectTrigger.displayName = "SelectTrigger";

const SelectScrollUpButton = ({ className }: { className?: string }) => {
  return (
    <div
      className={cn(
        "flex cursor-default items-center justify-center py-1",
        className
      )}
    >
      <ChevronUp className="w-4 h-4" />
    </div>
  );
};
SelectScrollUpButton.displayName = "SelectScrollUpButton";

const SelectScrollDownButton = ({ className }: { className?: string }) => {
  return (
    <div
      className={cn(
        "flex cursor-default items-center justify-center py-1",
        className
      )}
    >
      <ChevronDown className="w-4 h-4" />
    </div>
  );
};
SelectScrollDownButton.displayName = "SelectScrollDownButton";

interface SelectContentProps extends React.HTMLAttributes<HTMLDivElement> {
  isOpen?: boolean;
  position?: "item-aligned" | "popper";
}

const SelectContent = React.forwardRef<HTMLDivElement, SelectContentProps>(
  ({ className, children, isOpen, position = "popper", ...props }, ref) => {
    if (!isOpen) return null;

    return (
      <div className="fixed inset-0 z-50" onClick={(e) => e.stopPropagation()}>
        <div
          ref={ref}
          className={cn(
            "absolute z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md",
            className
          )}
          style={{ top: "calc(100% + 0.5rem)", left: 0, width: "100%" }}
          {...props}
        >
          <div className="max-h-[--radix-select-content-available-height] overflow-y-auto">
            <SelectScrollUpButton />
            <div className="p-1">{children}</div>
            <SelectScrollDownButton />
          </div>
        </div>
      </div>
    );
  }
);
SelectContent.displayName = "SelectContent";

const SelectLabel = React.forwardRef<
  HTMLLabelElement,
  React.LabelHTMLAttributes<HTMLLabelElement>
>(({ className, ...props }, ref) => (
  <label
    ref={ref}
    className={cn("px-2 py-1.5 text-sm font-semibold", className)}
    {...props}
  />
));
SelectLabel.displayName = "SelectLabel";

interface SelectItemProps extends React.LiHTMLAttributes<HTMLLIElement> {
  value: string;
  onValueChange?: (value: string) => void;
  selectedValue?: string;
}

const SelectItem = React.forwardRef<HTMLLIElement, SelectItemProps>(
  (
    { className, children, value, onValueChange, selectedValue, ...props },
    ref
  ) => {
    const isSelected = selectedValue === value;

    return (
      <li
        ref={ref}
        className={cn(
          "relative flex w-full cursor-pointer select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground",
          isSelected && "bg-accent text-accent-foreground",
          className
        )}
        onClick={() => onValueChange?.(value)}
        {...props}
      >
        <span className="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
          {isSelected && <Check className="w-4 h-4" />}
        </span>
        <span>{children}</span>
      </li>
    );
  }
);
SelectItem.displayName = "SelectItem";

const SelectSeparator = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn("-mx-1 my-1 h-px bg-muted", className)}
    {...props}
  />
));
SelectSeparator.displayName = "SelectSeparator";

export {
  Select,
  SelectGroup,
  SelectValue,
  SelectTrigger,
  SelectContent,
  SelectLabel,
  SelectItem,
  SelectSeparator,
  SelectScrollUpButton,
  SelectScrollDownButton,
};
